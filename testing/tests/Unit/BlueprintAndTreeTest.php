<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Definitions;

it('passes the documented blueprint example', function (): void {
    GcegoTest::blueprint(Definitions::subjects())
        ->assertValid()
        ->assertName('subjects')
        ->assertHasComponent('table')
        ->assertHasComponent('form')
        ->assertHasComponent('source')
        ->assertMissingComponent('navigation')
        ->assertStableIdentity('subjects');
});

it('reports invalid blueprints with their error codes', function (): void {
    GcegoTest::blueprint(new Blueprint('Bad Name'))->assertInvalid('component.invalid_value');

    expect(failure(fn () => GcegoTest::blueprint(new Blueprint('Bad Name'))->assertValid()))->toContain('GCEGO BLUEPRINT ASSERTION FAILED');
    expect(failure(fn () => GcegoTest::blueprint(Definitions::subjects())->assertHasComponent('navigation')))->toContain('roles:');
});

it('opens a part as a component testable', function (): void {
    GcegoTest::blueprint(Definitions::subjects())->part('table')->assertChildCount(3)->assertHasChild('name');
});

it('walks component trees by child name and relative path', function (): void {
    $tree = new Component(new Property(Component::Name, 'dashboard'), new Component(new Property(Component::Name, 'stats'), new Component(new Property(Component::Name, 'students'))));

    GcegoTest::component($tree)
        ->assertChildCount(1)
        ->assertHasChild('stats')
        ->assertHasChild('stats.students')
        ->assertMissingChild('stats.teachers')
        ->assertContainsType(Component::class, 3)
        ->assertNoDuplicateIds()
        ->child('stats')->assertName('stats')->assertChildCount(1);
});

it('detects duplicate identifiers in a tree', function (): void {
    $tree = new Component(new Property(Component::Id, 'same'), new Component(new Property(Component::Id, 'same')));

    expect(failure(fn () => GcegoTest::component($tree)->assertNoDuplicateIds()))->toContain('same');
});
