<?php

declare(strict_types=1);

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Tests\Fixtures\Models\User;
use Gcego\Workflows\Workflow;
use Illuminate\Support\Facades\Gate;

/**
 * Run a README example as written, with its variables defined.
 *
 * @param  array<string, mixed>  $variables
 */
function readmeExample(string $name, array $variables = []): mixed
{
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $code = $match[1] ?? 'throw new RuntimeException("missing example");';

    return (static function () use ($code, $variables): mixed {
        extract($variables);

        return eval($code);
    })();
}

it('declares the documented workflow without problems', function (): void {
    $workflow = readmeExample('workflow');

    expect($workflow)->toBeInstanceOf(Workflow::class)
        ->and($workflow->validate()->errors())->toBe([])
        ->and($workflow->workflowName())->toBe('institution-registration')
        ->and($workflow->initialState())->toBe('draft')
        ->and($workflow->approval('academic-review')?->stages()[0]->requiredApprovals())->toBe(2);
});

it('runs the documented engine example to approval', function (): void {
    $world = world();
    app(RegistryInterface::class)->register(readmeExample('workflow'));
    Gate::define('applications.review', static fn (User $user): bool => str_starts_with($user->name, 'Reviewer'));
    actAs($world['editor']);

    $state = readmeExample('engine', [
        'application' => $world['alpha'],
        'editor' => $world['editor'],
        'reviewer' => $world['reviewer1'],
        'firstReviewer' => $world['reviewer2'],
        'secondReviewer' => $world['reviewer3'],
    ]);

    expect($state)->toBe('approved');
});

it('declares the documented task state', function (): void {
    $state = readmeExample('task');

    expect($state)->toBeInstanceOf(State::class)->and($state->validate()->errors())->toBe([])
        ->and($state->tasks()[0]->priority())->toBe('high')->and($state->tasks()[0]->requirements())->toHaveCount(1);
});
