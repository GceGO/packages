<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Registry;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;

it('asserts what the registry holds and how identifiers resolve', function (): void {
    GcegoTest::registry()
        ->assertHas('blueprint:subjects')
        ->assertHas('subjects')
        ->assertKind('blueprint:subjects', 'blueprint')
        ->assertResolves('blueprint:subjects', Blueprint::class)
        ->assertMissing('blueprint:teachers')
        ->assertNotFound('blueprint:subjectz', 'blueprint:subjects')
        ->assertNoFailures()
        ->assertHealthy();
});

it('registers at runtime and refuses duplicates and invalid definitions', function (): void {
    GcegoTest::registry()
        ->register(new Blueprint('teachers'))
        ->assertHas('blueprint:teachers')
        ->assertRejectsDuplicate(new Blueprint('subjects'))
        ->assertRejectsInvalid(new Blueprint('Not Valid'));
});

it('reports a missing definition with suggestions', function (): void {
    expect(failure(fn () => GcegoTest::registry()->assertHas('blueprint:subjectz')))->toContain('similar:')->toContain('blueprint:subjects');
});

it('discovers definitions from an isolated workspace, never the application', function (): void {
    $fixture = GcegoTest::fixtures()->registry()
        ->definition('Definitions/teachers.php', "new Blueprint('teachers')", [Blueprint::class])
        ->root('Definitions', 'blueprint', 'file');
    $fixture->apply(app());

    GcegoTest::registry()->assertHas('blueprint:teachers')->assertMissing('blueprint:subjects')->assertOrigin('discovery');

    expect(config('gcego-registry.discovery.roots')[0]['path'])->toStartWith($fixture->workspace()->root);

    GcegoTest::fixtures()->cleanup();

    expect($fixture->workspace()->exists())->toBeFalse();
});

it('keeps registry state from leaking: refresh() drops resolved definitions', function (): void {
    $registry = app(Registry::class);
    $first = $registry->get('blueprint:subjects');
    GcegoTest::reset();

    $again = $registry->get('blueprint:subjects');

    expect($again)->not->toBe($first)
        ->and($again)->toBeInstanceOf(Blueprint::class)
        ->and($again instanceof Blueprint ? $again->normalize() : null)->toBe(Definitions::subjects()->normalize());
});
