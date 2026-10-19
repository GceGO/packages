<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Actions\ActionIntent;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\ActionsConfig;
use Gcego\Actions\BulkDelete;
use Gcego\Actions\BulkRestore;
use Gcego\Actions\Configuration\Confirmation;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Configuration\Lifecycle;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\ForceDelete;
use Gcego\Actions\Handlers\CreateRecord;
use Gcego\Actions\Handlers\DeleteRecord;
use Gcego\Actions\Restore;
use Gcego\Actions\Tests\Fixtures\PublishSubject;
use Gcego\Actions\View;
use Gcego\Components\Property;
use Gcego\Luminaries\Icons\Alias;
use Gcego\Luminaries\Icons\Heroicon;

it('gives every built-in action conventional defaults', function (Action $action, string $name, string $label, string $ability, ?bool $record, string $intent, ?string $confirmationKey): void {
    expect($action->actionName())->toBe($name)
        ->and($action->label())->toBe($label)
        ->and($action->ability())->toBe($ability)
        ->and($action->requiresRecord())->toBe($record)
        ->and($action->intent()->value)->toBe($intent)
        ->and($action::confirmationKey())->toBe($confirmationKey)
        ->and($action->handler())->not->toBeNull()
        ->and($action->validate()->isValid())->toBeTrue();
})->with([
    'create' => [new Create, 'create', 'Create', 'create', false, 'primary', null],
    'edit' => [new Edit, 'edit', 'Edit', 'update', true, 'secondary', null],
    'view' => [new View, 'view', 'View', 'view', true, 'secondary', null],
    'delete' => [new Delete, 'delete', 'Delete', 'delete', true, 'destructive', 'delete'],
    'restore' => [new Restore, 'restore', 'Restore', 'restore', true, 'secondary', 'restore'],
    'force delete' => [new ForceDelete, 'force-delete', 'Delete permanently', 'forceDelete', true, 'destructive', 'force_delete'],
    'bulk delete' => [new BulkDelete, 'bulk-delete', 'Delete selected', 'delete', false, 'destructive', 'delete'],
    'bulk restore' => [new BulkRestore, 'bulk-restore', 'Restore selected', 'restore', false, 'secondary', 'restore'],
]);

it('maps defaults to placements, handlers and input needs', function (): void {
    expect((new Create)->placements())->toBe([ActionPlacement::Header])
        ->and((new Edit)->placements())->toBe([ActionPlacement::Row, ActionPlacement::Record])
        ->and((new BulkDelete)->placements())->toBe([ActionPlacement::Bulk])
        ->and((new Create)->handler()?->handler)->toBe(CreateRecord::class)
        ->and((new Delete)->handler()?->handler)->toBe(DeleteRecord::class)
        ->and(Create::requiresInput())->toBeTrue()
        ->and(Edit::requiresInput())->toBeTrue()
        ->and(Delete::requiresInput())->toBeFalse()
        ->and(Restore::trashed())->toBe('only')
        ->and(Delete::trashed())->toBeNull();
});

it('accepts Property customization and shorthand', function (): void {
    $delete = new Delete(new Property(Delete::Label, 'Remove Subject'), new Property(Delete::Confirmation, true));
    $short = new Delete('Remove');
    $custom = new Action('publish', 'Publish', new Handler(PublishSubject::class));

    expect($delete->label())->toBe('Remove Subject')
        ->and($delete->confirmationSetting())->toBeTrue()
        ->and($delete->actionName())->toBe('delete')
        ->and($short->label())->toBe('Remove')
        ->and($custom->actionName())->toBe('publish')
        ->and($custom->label())->toBe('Publish')
        ->and($custom->ability())->toBeNull()
        ->and($custom->requiresRecord())->toBeNull()
        ->and((new Action(new Property(Action::Name, 'send-report')))->label())->toBe('Send report')
        ->and((new Action('x', new Property(Action::Intent, 'destructive')))->intent())->toBe(ActionIntent::Destructive);
});

it('validates definitions without executing anything', function (): void {
    $queries = queriesDuring(function () use (&$invalid): void {
        $invalid = (new Action('Bad Name'))->validate();
        new Action('publish', new Handler(fn () => throw new RuntimeException('must not run')));
    });

    expect($queries)->toBe([])
        ->and($invalid->isValid())->toBeFalse()
        ->and((new Action(new Property(Action::Label, 'No name')))->validate()->isValid())->toBeFalse()
        ->and((new Action('x', new Property(Action::Placement, 'Not Valid')))->validate()->isValid())->toBeFalse()
        ->and((new Action('x', new Property(Action::Intent, 'loud')))->validate()->isValid())->toBeFalse()
        ->and((new Action('x', new Property(Action::Ability, 'drop table')))->validate()->isValid())->toBeFalse();
});

it('never serializes handlers or hooks', function (): void {
    $action = new Action('publish', new Handler(fn () => 'secret-code'), new Lifecycle(prepare: fn () => null));
    $json = (string) json_encode($action->normalize());

    expect($json)->toContain('"handler":"closure"')
        ->and($json)->toContain('"hooks":["prepare"]')
        ->and($json)->not->toContain('secret-code')
        ->and((new Handler(PublishSubject::class))->describe())->toBe(PublishSubject::class);
});

it('rejects handler classes that are not ActionHandlers', function (): void {
    new Handler(stdClass::class); // @phpstan-ignore argument.type
})->throws(InvalidActionException::class, 'does not implement');

it('validates input rules, groups and requests', function (): void {
    expect((new Input(['name' => 'required']))->fields())->toBe(['name'])
        ->and((new Input(['is_published' => 'boolean'], labels: ['is_published' => 'Published']))->label('is_published'))->toBe('Published')
        ->and(fn () => new Input([]))->toThrow(InvalidActionException::class)
        ->and(fn () => new Input(['bad key' => 'required']))->toThrow(InvalidActionException::class)
        ->and((new ActionGroup('more', 'More', new Edit, new Delete))->actions())->toHaveCount(2)
        ->and((new ActionGroup('empty'))->validate()->isValid())->toBeFalse()
        ->and(fn () => new ActionRequest('blueprint:x', 'delete', idempotencyKey: 'short'))->toThrow(InvalidActionException::class)
        ->and((new Confirmation('Sure?'))->normalize()['title'])->toBe('Sure?');
});

it('validates configuration and placements', function (): void {
    expect(ActionsConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-actions.php'))->toEqual(new ActionsConfig)
        ->and(fn () => ActionsConfig::fromArray(['bulk' => ['chunk_size' => 500]]))->toThrow(InvalidActionException::class, 'must not exceed 100')
        ->and(fn () => ActionsConfig::fromArray(['confirmation' => ['delete' => 'yes']]))->toThrow(InvalidActionException::class)
        ->and(fn () => ActionsConfig::fromArray(['bulk' => ['max_selection' => 0]]))->toThrow(InvalidActionException::class);

    $placements = new ActionPlacement;
    $placements->register('kanban.card');

    expect($placements->has('kanban.card'))->toBeTrue()
        ->and($placements->has(ActionPlacement::Row))->toBeTrue()
        ->and(fn () => $placements->register('Bad Placement'))->toThrow(InvalidActionException::class);
});

it('takes typed Luminaries icons and validates every icon name', function (): void {
    $action = new Action('archive', new Property(Action::Icon, Heroicon::ArchiveBox));
    $group = new ActionGroup('more', 'More', new Property(ActionGroup::Icon, Alias::ActionsMore), new Edit);

    expect($action->get(Action::Icon))->toBe('heroicon:archive-box')
        ->and($action->validate()->isValid())->toBeTrue()
        ->and($group->get(ActionGroup::Icon))->toBe('actions.more')
        ->and($group->validate()->isValid())->toBeTrue()
        ->and((new ActionGroup('more', 'More', new Property(ActionGroup::Icon, '"><svg onload=x>'), new Edit))->validate()->isValid())->toBeFalse();
});
