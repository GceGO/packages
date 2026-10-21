<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionStatus;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\Permission;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

beforeEach(function (): void {
    $this->world = world();
});

it('authorizes generated pages with the model policy by default', function (string $user, string $uri, int $status): void {
    asMember($this->world[$user])->get($uri)->assertStatus($status);
})->with([
    'reader lists' => ['reader', '/admin/subjects', 200],
    'visitor may not list (viewAny)' => ['visitor', '/admin/subjects', 403],
    'reader may not open create (create)' => ['reader', '/admin/subjects/create', 403],
    'editor opens create' => ['editor', '/admin/subjects/create', 200],
    'reader views a record (view)' => ['reader', '/admin/subjects/1', 200],
    'reader may not edit (update)' => ['reader', '/admin/subjects/1/edit', 403],
    'nobody sees Locked (view)' => ['admin', '/admin/subjects/3', 403],
    'other tenant record' => ['admin', '/admin/subjects/4', 404],
    'other tenant record, edit' => ['admin', '/admin/subjects/4/edit', 404],
]);

it('sends guests to sign in', function (): void {
    world();
    $this->get('/admin/subjects')->assertRedirect('/login');
});

it('authorizes actions again when they run (delete needs the delete ability)', function (): void {
    actAs($this->world['editor']);
    expect(runAction('page:academics-subjects-view', 'delete', 1)->status)->toBe(ActionStatus::Denied)
        ->and(Subject::query()->find(1))->not->toBeNull();

    actAs($this->world['admin']);
    expect(runAction('page:academics-subjects-view', 'delete', 4)->status)->toBe(ActionStatus::NotFound);
    expect(runAction('page:academics-subjects-view', 'delete', 2)->status)->toBe(ActionStatus::Success)
        ->and(Subject::query()->find(2))->toBeNull();
});

it('adds module and resource requirements and per-operation permissions to every page', function (): void {
    $this->reboot([]);
    world();
    modules()->register(new Module(
        new Property(Module::Name, 'reports'),
        new Permission('academics.reports'),
        new Resource(
            new Property(Resource::Name, 'archive'),
            new Property(Resource::Slug, 'archive'),
            new Property(Resource::Permissions, ['index' => 'academics.reports']),
            new Source(Subject::class),
            new Table(new Text('name', 'Name')),
        ),
    ));

    $page = app(PageDefinitions::class)->resolve('page:reports-archive');
    $requirements = array_map(static fn ($node): string => $node->describe(), array_values(array_filter($page->configurations()->all(), static fn ($node): bool => $node instanceof Requirement)));

    expect($requirements)->toBe(['permission academics.reports', 'permission academics.reports', 'ability viewAny on Subject']);
});

it('leaves authorization to the declarations when authorize_by_default is off', function (): void {
    $this->reboot(['gcego-modules.security.authorize_by_default' => false]);
    $world = world();

    // No requirement at all: gcego/pages' default ("deny") applies.
    asMember($world['admin'])->get('/admin/subjects')->assertForbidden();
});

it('never grants anything because a route or a link exists', function (): void {
    $html = squish((string) asMember($this->world['reader'])->get('/admin/subjects/1')->getContent());

    expect($html)->not->toContain('href="/admin/subjects/1/edit"');
});
