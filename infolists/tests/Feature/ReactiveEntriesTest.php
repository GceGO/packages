<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Exceptions\EvaluationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Patching\SchemaPatch;
use Gcego\Data\Source;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Infolists\Tests\Fixtures\Models\Topic;
use Gcego\Infolists\Tests\Fixtures\Models\User;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

function registerCourse(Infolist $infolist): void
{
    app(Registry::class)->register(new Blueprint('course', new Ability('viewAny', Subject::class), new Source(Subject::class), $infolist));
}

it('builds infolists Filament-style with make()', function (): void {
    $infolist = Infolist::make()->schema([
        Section::make('Details')->columns(2)->schema([Text::make('name'), Text::make('code')]),
    ])->build();

    expect($infolist->validate()->isValid())->toBeTrue()
        ->and(infolists()->schema($infolist)->paths ?? null)->not->toBeFalse();
});

it('evaluates per-record closures with the record injected by type', function (): void {
    registerCourse(Infolist::make()->schema([
        Text::make('name')->label(fn (Subject $record): string => 'Name of '.$record->code)->tooltip(fn (string $state): string => 'Called '.$state),
        Text::make('summary')->state(fn (Subject $record): string => $record->name.' ('.$record->code.')'),
        Text::make('credits')->formatStateUsing(fn (mixed $state, Subject $record): string => $state.' credits for '.$record->code),
        Text::make('internal_code')->placeholder(fn (Subject $record): string => 'none for '.$record->code)->state(fn (): ?string => null),
    ])->build());

    $html = details('course', $this->world['subject']->id);

    expect($html)->toContain('Name of MTH101')
        ->and($html)->toContain('title="Called Algebra"')
        ->and($html)->toContain('Algebra (MTH101)')
        ->and($html)->toContain('3 credits for MTH101')
        ->and($html)->toContain('none for MTH101');
});

it('shows and hides entries and layouts with closures over the record and the user', function (): void {
    registerCourse(Infolist::make()->schema([
        Text::make('name'),
        Text::make('code')->visible(fn (Subject $record): bool => $record->status === 'draft'),
        Text::make('status')->hidden(fn (?User $user): bool => $user?->name === 'Editor'),
        Section::make('Pricing')->visible(fn (Subject $record): bool => $record->credits > 1)->schema([Text::make('fee')]),
        Section::make('Archive')->hidden(fn (Subject $record): bool => $record->status === 'published')->schema([Text::make('notes')]),
    ])->build());

    $html = details('course', $this->world['subject']->id);

    expect($html)->not->toContain('MTH101')
        ->and($html)->not->toContain('published</')
        ->and($html)->toContain('Pricing')
        ->and($html)->not->toContain('Archive');
});

it('gives entries inside repeatable items the item as $record and the owner as $root', function (): void {
    registerCourse(Infolist::make()->schema([
        RepeatableEntry::make('topics')->schema([
            Text::make('title')->formatStateUsing(fn (string $state, Topic $record, Subject $root): string => $root->code.'/'.$state.'#'.$record->position),
        ]),
    ])->build());

    $html = details('course', $this->world['subject']->id);

    expect($html)->toContain('MTH101/Equations#1')
        ->and($html)->toContain('MTH101/Matrices#2');
});

it('checks per-record closure results against the property definition', function (): void {
    $entry = Text::make('name')->tooltip(fn (): int => 42)->build();

    expect(fn () => $entry->forRecord('Algebra', $this->world['subject']))->toThrow(EvaluationException::class, 'Expected: bool|string');
});

it('lets other modules extend a blueprint infolist through Components::extend()', function (): void {
    Components::extend('course.infolist', fn (SchemaPatch $patch): SchemaPatch => $patch
        ->insertAfter('name', Text::make('code'))
        ->modify('name', fn (ComponentBuilder $name): ComponentBuilder => $name->label('Course')));
    registerCourse(Infolist::make()->schema([Text::make('name')])->build());

    $html = details('course', $this->world['subject']->id);

    expect($html)->toContain('Course</dt>')
        ->and($html)->toContain('MTH101');
});
