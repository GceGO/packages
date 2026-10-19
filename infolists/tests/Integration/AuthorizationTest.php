<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Data\Source;
use Gcego\Infolists\Configuration\Access;
use Gcego\Infolists\Configuration\Resolve;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Tests\Fixtures\Blueprints;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Registry;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('hides unreadable entries entirely and masks masked ones', function (): void {
    $html = details('subjects', $this->world['subject']->id);

    expect($html)->not->toContain('TOP SECRET')
        ->and($html)->not->toContain('Secret notes')
        ->and($html)->not->toContain('B7')
        ->and($html)->toContain('data-gcego-entry="salary_band"')
        ->and($html)->toContain('aria-label="Hidden value"')
        ->and($html)->not->toContain('INT-9')
        ->and($html)->not->toContain('Restricted');
});

it('shows protected entries and sections once permission is granted', function (): void {
    Gate::define('subjects.secrets', fn ($user): bool => $user->name === 'Editor');
    actAs($this->world['editor']);
    $html = details('subjects', $this->world['subject']->id);

    expect($html)->toContain('TOP SECRET')
        ->and($html)->toContain('B7')
        ->and($html)->toContain('INT-9')
        ->and($html)->toContain('Restricted');
});

it('never reads unauthorized values', function (): void {
    $reads = [];
    $infolist = new Infolist(new Text('secret_notes', new Access(read: new Permission('subjects.secrets')), new Resolve(function () use (&$reads) {
        $reads[] = 'read';

        return 'x';
    })));
    app(Registry::class)->register(new Blueprint('probe', new Ability('viewAny', Subject::class), new Source(Subject::class), $infolist));

    details('probe', $this->world['subject']->id);

    expect($reads)->toBe([]);
});

it('does not leak hidden model attributes', function (): void {
    app(Registry::class)->register(new Blueprint('leaky', new Ability('viewAny', Subject::class), new Source(Subject::class), new Infolist(new Text('api_token', 'Token'), new Text('name'))));

    expect(details('leaky', $this->world['subject']->id))->not->toContain('tok_live_123')
        ->and(details('subjects', $this->world['subject']->id))->not->toContain('sk_live_SECRET');
});

it('evaluates visibility conditions on the record without granting access', function (): void {
    expect(details('subjects', $this->world['subject']->id))->not->toContain('Archive reason');

    $this->world['subject']->update(['status' => 'archived', 'archive_reason' => 'Replaced']);

    expect(details('subjects', $this->world['subject']->id))->toContain('Replaced');
});

it('isolates tenants', function (): void {
    actAs($this->world['south_user'], 2);

    expect(details('subjects', $this->world['south']->id))->toContain('South Physics');

    expect(statusOf(fn () => infolists()->render('subjects', $this->world['subject']->id)))->toBe(404);
});

it('escapes record values everywhere', function (): void {
    $this->world['subject']->update(['name' => '<script>alert(1)</script>', 'code' => '"><img src=x onerror=alert(1)>', 'notes' => '<b>bold</b>', 'status' => '<i>x</i>']);
    $html = details('subjects', $this->world['subject']->id);

    expect($html)->not->toContain('<script>alert(1)')
        ->and($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('<b>bold</b>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('keeps the fixture definition healthy', function (): void {
    expect(Blueprints::infolist()->validate()->isValid())->toBeTrue();
});
