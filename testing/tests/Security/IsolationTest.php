<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\PasswordInput;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

/*
 * Negative tests: every one attempts something an attacker would and passes
 * only when the server refuses. Hiding a control is never enough.
 */

it('refuses another institution through every layer', function (): void {
    $world = world();
    $source = new Source(Subject::class);
    $foreign = $world['southern'];

    GcegoTest::tenant(1)->actingAs($world['admin'])
        ->assertCannotAccessTenant(2)
        ->assertCannotSee($source, [$foreign])
        ->assertActionRefused('subjects', 'delete', $foreign->id)
        ->assertActionRefused('subjects', 'rename', $foreign->id, ['name' => 'Hijacked'])
        ->assertSelectionRefused('subjects', 'bulk-delete', [$foreign->id]);

    GcegoTest::data($source)->actingAs($world['admin'], 1)->assertNotFound($foreign->id)->query(['per_page' => 100])->assertMissing([$foreign]);
    GcegoTest::table('subjects')->actingAs($world['admin'], 1)->query(['search' => 'Southern'])->assertEmpty();
    GcegoTest::infolist('subjects')->actingAs($world['admin'], 1)->assertRecordNotFound($foreign->id);
    GcegoTest::security()->actingAs($world['admin'], 1)->assertRecordHidden($source, $foreign->id);

    expect($foreign->refresh()->name)->toBe('Southern')->and(Subject::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('conceals foreign records as missing, not forbidden', function (): void {
    $world = world();
    $source = new Source(Subject::class);

    GcegoTest::security()->actingAs($world['admin'], 1)->assertRecordHidden($source, $world['southern']->id)->assertRecordHidden($source, 987654);
});

it('refuses forged identifiers', function (string|int $key): void {
    $world = world();

    GcegoTest::data(new Source(Subject::class))->actingAs($world['admin'], 1)->assertNotFound($key);
    GcegoTest::action('subjects', 'publish')->actingAs($world['admin'], 1)->record($key)->execute()->assertNotFound();
})->with(['injection' => '1 OR 1=1', 'traversal' => '../../etc/passwd', 'negative' => -1, 'huge' => PHP_INT_MAX, 'empty' => '']);

it('refuses a privilege escalation through submitted fields', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'create')->actingAs($world['editor'], 1)
        ->input(['name' => 'Physics', 'institution_id' => 2, 'published' => true])
        ->execute()->assertDenied();

    expect(Subject::query()->where('name', 'Physics')->exists())->toBeFalse();
});

it('refuses users without the ability even when they can see the record', function (): void {
    $world = world();

    GcegoTest::action('subjects', 'delete')->actingAs($world['student'], 1)->record($world['algebra'])->confirmed()->execute()->assertDenied();
    // A guest has no tenant, so the record lookup fails closed before authorization.
    GcegoTest::action('subjects', 'delete')->actingAs(null)->record($world['algebra'])->confirmed()->execute()->assertNotFound();

    expect(Subject::query()->whereKey($world['algebra']->id)->exists())->toBeTrue();
});

it('re-checks the user when a confirmation token is replayed by someone else', function (): void {
    $world = world();
    $first = GcegoTest::action('subjects', 'delete')->actingAs($world['admin'], 1)->record($world['draft'])->execute()->assertConfirmationRequired();
    $token = $first->result()->confirmation['token'] ?? '';

    GcegoTest::security()->actingAs($world['editor'], 1);
    $replayed = app(ActionManager::class)->execute(new ActionRequest('subjects', 'delete', $world['draft']->id, confirmation: is_string($token) ? $token : ''));

    expect($replayed->successful())->toBeFalse()->and(Subject::query()->whereKey($world['draft']->id)->exists())->toBeTrue();
});

it('never prints secrets in assertion failures', function (): void {
    $world = world();
    $world['admin']->forceFill(['password' => 'plain-text-password', 'remember_token' => 'remember-me-token'])->save();

    $message = failure(fn () => GcegoTest::component(new Component(new Property('api_token', 'tok_live_secret')))->assertNormalized(['properties' => ['api_token' => 'other']]));

    expect($message)->not->toContain('tok_live_secret')->toContain('[redacted]');

    $form = failure(fn () => GcegoTest::form(new Form(new PasswordInput('password', 'Password', new Property(PasswordInput::Required, true)), new TextInput('name', 'Name', new Property(TextInput::MaxLength, 3))))->fill(['password' => 'hunter2-secret', 'name' => 'too long'])->assertValid());

    expect($form)->not->toContain('hunter2-secret')->toContain('[redacted]');
});
