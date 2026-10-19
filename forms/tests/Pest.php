<?php

declare(strict_types=1);

use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\Form;
use Gcego\Forms\FormManager;
use Gcego\Forms\Tests\Fixtures\Blueprints;
use Gcego\Forms\Tests\Fixtures\Models\Category;
use Gcego\Forms\Tests\Fixtures\Models\Membership;
use Gcego\Forms\Tests\Fixtures\Models\Subject;
use Gcego\Forms\Tests\Fixtures\Models\Tag;
use Gcego\Forms\Tests\Fixtures\Models\User;
use Gcego\Forms\Tests\TestCase;
use Gcego\Runtime\Runtime;
use Gcego\Security\SecurityManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * @return array{reader: User, editor: User, admin: User, categories: array<string, Category>, tags: array<string, Tag>, subject: Subject, south: Subject}
 */
function world(): array
{
    $users = [];

    foreach (['reader' => 'Reader', 'editor' => 'Editor', 'admin' => 'Admin'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    $categories = [
        'science' => Category::query()->create(['name' => 'Science', 'institution_id' => 1]),
        'arts' => Category::query()->create(['name' => 'Arts', 'institution_id' => 1]),
        'retired' => Category::query()->create(['name' => 'Retired', 'institution_id' => 1, 'active' => false]),
        'south' => Category::query()->create(['name' => 'South only', 'institution_id' => 2]),
    ];
    $tags = ['core' => Tag::query()->create(['label' => 'Core']), 'lab' => Tag::query()->create(['label' => 'Lab'])];

    $subject = new Subject(['name' => 'Algebra', 'code' => 'MTH101', 'secret_notes' => 'TOP SECRET', 'password' => 'hashed-value', 'category_id' => $categories['science']->id, 'objectives' => [['title' => 'Equations', 'details' => 'Linear']]]);
    $subject->institution_id = 1;
    $subject->save();
    $subject->tags()->attach($tags['core']);

    $south = new Subject(['name' => 'South Physics', 'code' => 'PHY101']);
    $south->institution_id = 2;
    $south->save();

    return [...$users, 'categories' => $categories, 'tags' => $tags, 'subject' => $subject, 'south' => $south];
}

function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
}

function forms(): FormManager
{
    return app(FormManager::class);
}

/**
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    $queries = [];
    $recording = true;
    Event::listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$queries, &$recording): void {
        if ($recording) {
            $queries[] = $query->sql;
        }
    });

    try {
        $callback();
    } finally {
        $recording = false;
    }

    return $queries;
}

/**
 * A valid submission for Blueprints::form().
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function valid(array $overrides = []): array
{
    return [...['name' => 'Biology', 'code' => 'BIO101', 'kind' => 'core', 'credits' => '3', 'level' => '2'], ...$overrides];
}

function subjectsForm(): Form
{
    return Blueprints::form();
}

function choiceField(string $path): ChoiceField
{
    $field = forms()->schema(subjectsForm())->entry($path)?->field;

    return $field instanceof ChoiceField ? $field : throw new LogicException($path.' is not a choice field.');
}

/**
 * A form rendered outside Livewire, with whitespace collapsed.
 */
function html(Form $form): string
{
    return (string) preg_replace('/\s+/', ' ', app(Runtime::class)->render($form)->toHtml());
}
