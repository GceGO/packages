<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Livewire\PageHost;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Tests\Fixtures\Models\User;
use Gcego\Registry\Registry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('answers each request with the status its authorization deserves', function (string $user, string $uri, int $status): void {
    $response = asMember($this->world[$user], $user === 'south' ? 2 : 1)->get($uri);

    expect($response->getStatusCode())->toBe($status);
})->with([
    'admin, list' => ['admin', '/admin/subjects', 200],
    'visitor, list: no permission' => ['visitor', '/admin/subjects', 403],
    'stranger, not a member of the selected tenant' => ['stranger', '/admin/subjects', 404],
    'reader, edit: policy denies update' => ['reader', '/admin/subjects/1/edit', 403],
    'editor, edit' => ['editor', '/admin/subjects/1/edit', 200],
    'admin, view a record the policy hides' => ['admin', '/admin/subjects/3', 403],
    'admin, another tenant\'s record' => ['admin', '/admin/subjects/4', 404],
    'south, own tenant\'s record' => ['south', '/admin/subjects/4', 200],
    'south, another tenant\'s record' => ['south', '/admin/subjects/1', 404],
    'admin, missing record' => ['admin', '/admin/subjects/999', 404],
    'reader, create: no permission' => ['reader', '/admin/subjects/create', 403],
    'editor, reports: no permission' => ['editor', '/admin/reports/overview', 403],
    'admin, own institution\'s members' => ['admin', '/admin/institutions/1/members', 200],
    'admin, a foreign institution\'s members' => ['admin', '/admin/institutions/2/members', 404],
]);

it('sends guests to the login page', function (): void {
    $this->get('/admin/subjects')->assertRedirect('/login');
});

it('denies pages without requirements by default, and follows the configured default', function (string $default, ?int $member, ?int $guest): void {
    app(Registry::class)->register(new Page(new Property(Page::Name, 'open'), new Content('Open to whom?')));
    app()->instance(PagesConfig::class, PagesConfig::fromArray(['authorization' => ['default' => $default]]));

    actAs($this->world['reader']);
    $status = statusOf(fn () => pages()->open('open'));
    actAs(null, null);
    auth()->logout();
    app()->forgetScopedInstances();
    $guestStatus = statusOf(fn () => pages()->open('open'));

    expect($status)->toBe($member)->and($guestStatus)->toBe($guest);
})->with([
    'deny' => ['deny', 403, 401],
    'authenticated' => ['authenticated', null, 401],
    'public' => ['public', null, null],
]);

it('raises an authentication exception, not a 403, for guests', function (): void {
    expect(fn () => pages()->open('subjects'))->toThrow(AuthenticationException::class);
});

it('never resolves pages from client-supplied names that are not page identities', function (string $identity): void {
    actAs($this->world['admin']);

    expect(statusOf(fn () => pages()->open($identity)))->toBe(404);

    Livewire::test(PageHost::class, ['page' => $identity])->assertStatus(404);
})->with([
    'unknown page' => ['page:unknown'],
    'class name' => [User::class],
    'blueprint identity' => ['blueprint:subjects'],
    'path traversal' => ['page:../../etc/passwd'],
    'widget identity' => ['widget:subjects'],
    'empty' => [''],
]);

it('keeps locked host state locked', function (): void {
    actAs($this->world['admin']);
    $host = Livewire::test(PageHost::class, ['page' => 'subjects.view', 'record' => '1']);

    foreach (['page' => 'page:subjects-edit', 'record' => '4', 'parameters' => ['institution' => '2'], 'tab' => 'fees'] as $property => $value) {
        expect(fn () => $host->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
    }
});

it('reauthorizes the page on every Livewire update', function (): void {
    $page = asMember($this->world['admin'])->get('/admin/subjects/1')->assertOk()->getContent();
    $snapshot = snapshotOf((string) $page, 'gcego-page');

    expect($snapshot)->not->toBe('');
    livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertOk();

    Gate::define('subjects.view', static fn (): bool => false);

    livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertForbidden();
});

it('re-applies tenant scopes on every Livewire update', function (): void {
    $page = asMember($this->world['admin'])->get('/admin/subjects/1')->assertOk()->getContent();
    $snapshot = snapshotOf((string) $page, 'gcego-page');

    // The Admin's snapshot replayed with institution 2 selected, where the Admin is not a member: rejected, nothing rendered.
    $response = livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]], institution: 2);

    expect($response->getStatusCode())->toBe(404)
        ->and((string) $response->getContent())->not->toContain('Chemistry');
});

it('shows a missing-record state when the record disappears while the page is open', function (): void {
    $page = asMember($this->world['admin'])->get('/admin/subjects/2')->assertOk()->getContent();
    $snapshot = snapshotOf((string) $page, 'gcego-page');
    Subject::query()->whereKey(2)->delete();

    $html = squish((string) livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertOk()->json('components.0.effects.html'));

    expect($html)->toContain('This record is no longer available.')
        ->and($html)->toContain('<a href="/admin/subjects"')
        ->and($html)->not->toContain('Physics');
});

it('serializes identities and keys only, never models or decisions', function (): void {
    $html = (string) asMember($this->world['admin'])->get('/admin/subjects/1')->assertOk()->getContent();
    $snapshot = json_decode(snapshotOf($html, 'gcego-page'), true);

    expect(array_keys($snapshot['data']))->toBe(['page', 'record', 'parameters', 'tab', 'missing'])
        ->and($snapshot['data']['page'])->toBe('page:subjects-view')
        ->and($snapshot['data']['record'])->toBe('1')
        ->and(json_encode($snapshot))->not->toContain('Chemistry')
        ->and($html)->not->toContain('SECRET-NOTE')
        ->and(strlen(json_encode($snapshot)))->toBeLessThan(600);
});

it('escapes titles, descriptions, labels and content', function (): void {
    Subject::query()->whereKey(1)->update(['name' => '<script>alert(1)</script>', 'code' => '<img src=x onerror=alert(2)>']);

    $view = (string) asMember($this->world['admin'])->get('/admin/subjects/1')->getContent();
    $document = $this->world['documents'][0];
    $document->update(['code' => '<img src=x onerror=alert(2)>']);
    $catalogue = (string) asMember($this->world['admin'])->get('/admin/catalogue/'.$document->getKey())->getContent();
    $about = (string) asMember($this->world['admin'])->get('/admin/about')->getContent();

    expect($view)->not->toContain('<script>alert(1)</script>')
        ->and($view)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($catalogue)->not->toContain('<img src=x')
        ->and($catalogue)->toContain('Subject &lt;img src=x onerror=alert(2)&gt;')
        ->and($catalogue)->toContain('Code '.$document->getKey().' of &lt;b&gt;the&lt;/b&gt; catalogue')
        ->and($about)->toContain('It is &lt;b&gt;free&lt;/b&gt; &amp; open.')
        ->and($about)->not->toContain('<b>free</b>');
});

it('hides children whose own requirements deny the user, without failing the page', function (): void {
    $html = (string) asMember($this->world['admin'])->get('/admin/reports/overview')->assertOk()->getContent();

    expect($html)->toContain('Attendance is above 90%.')
        ->and($html)->not->toContain('Confidential finance summary.');
});

it('keeps decisions per user, tenant and request', function (): void {
    actAs($this->world['admin']);
    $admin = app(PageAuthorizer::class)->allows('page:subjects', pages()->resolve('subjects'));
    actAs($this->world['stranger'], null);
    $stranger = app(PageAuthorizer::class)->allows('page:subjects', pages()->resolve('subjects'));

    expect($admin)->toBeTrue()->and($stranger)->toBeFalse();
});
