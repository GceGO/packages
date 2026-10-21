<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Filters\DateFilter;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\Table;
use Gcego\Tables\TableManager;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('renders accessible custom controls instead of native selects and date inputs', function (): void {
    $html = Livewire::test(TableHost::class, ['definition' => 'subjects'])->html();

    expect($html)->not->toContain('<select')
        ->and($html)->not->toContain('type="date"')
        ->and($html)->toContain('role="listbox"')
        ->and($html)->toContain('aria-haspopup="listbox"')
        ->and($html)->toContain('aria-haspopup="dialog"')
        ->and($html)->toContain('x-data="gcegoDatePicker(')
        ->and($html)->toContain('x-data="gcegoListbox(')
        ->and($html)->toContain('data-gcego-controls');
});

it('keeps real checkboxes for selection, drawn as themed boxes', function (): void {
    $html = Livewire::test(TableHost::class, ['definition' => 'subjects'])->html();

    expect($html)->toContain('wire:model.live="selected"')
        ->and($html)->toContain('appearance-none')
        ->and($html)->toContain('indeterminate:bg-primary');
});

it('polls live tables and shows the live indicator, and leaves other tables static', function (): void {
    expect(Livewire::test(TableHost::class, ['definition' => 'subjects'])->html())->not->toContain('wire:poll');

    $table = app(TableManager::class)->resolve('subjects')->table;

    $validator = app(ComponentValidator::class);
    $tooFast = new Table(new Text('name'), new Property(Table::Poll, 2));
    $live = new Table(new Text('name'), new Property(Table::Poll, 30));

    expect($validator->validate($tooFast)->isValid())->toBeFalse()
        ->and($validator->validate($live)->isValid())->toBeTrue()
        ->and($live->get(Table::Poll))->toBe(30)
        ->and($table->get(Table::Poll))->toBeNull();
});

it('writes filter dates for people in indicators', function (): void {
    expect(DateFilter::display('2026-10-18'))->toBe('18 Oct 2026')
        ->and(DateFilter::display('not-a-date'))->toBe('not-a-date');
});
