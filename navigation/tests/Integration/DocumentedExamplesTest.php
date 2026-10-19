<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Registry\Registry;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('renders the documented examples as written', function (): void {
    $registry = app(Registry::class);
    $registry->register(new Navigation('basic',
        new Item('Dashboard', '/admin'),
        new Item('Students', '/admin/students'),
    ));
    $registry->register(new Navigation('grouped',
        new Group(
            'Academics',

            new Item('Subjects', '/admin/subjects'),
            new Item('Topics', '/admin/topics'),
        ),
    ));
    $registry->register(new Navigation('permitted',
        new Item(
            new Property(Item::Name, 'students'),
            new Property(Item::Label, 'Students'),
            new Property(Item::Route, 'admin.students.index'),
            new Property(Item::Permission, 'students.view'),
        ),
    ));
    onPage('/admin/students');

    expect(navigation()->render('basic')->toHtml())->toMatch('/<a href="\/admin\/students"[^>]*aria-current="page"/')
        ->and(navigation()->render('grouped')->toHtml())->toContain('Academics')
        ->and(navigation()->render('permitted')->toHtml())->toBe('');

    Gate::define('students.view', fn (): bool => true);
    actAs($this->world['teacher']);
    onPage('/admin/students');

    expect(navigation()->render('permitted')->toHtml())->toMatch('/<a href="\/admin\/students"[^>]*aria-current="page"/');
});
