<?php

declare(strict_types=1);

use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\Exceptions\UnsafeSvgException;
use Gcego\Luminaries\Tests\Fixtures\Livewire\IconToggle;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Livewire\Livewire;

it('renders semantic, canonical and custom icons through x-gcego-icon', function (string $name, string $canonical): void {
    $html = Blade::render('<x-gcego-icon :name="$name" />', ['name' => $name]);

    expect($html)->toContain('data-gcego-icon="'.$canonical.'"');
})->with([
    'alias' => ['actions.edit', 'heroicon:pencil-square'],
    'lucide' => ['lucide:graduation-cap', 'lucide:graduation-cap'],
    'heroicon' => ['heroicon:academic-cap', 'heroicon:academic-cap'],
    'custom' => ['custom:gcego-logo', 'custom:gcego-logo'],
]);

it('passes size, tone, class, label and stroke width to the renderer', function (): void {
    $html = Blade::render('<x-gcego-icon name="lucide:pencil" size="lg" tone="danger" class="ml-1" label="Edit" stroke-width="1.5" />');

    expect($html)->toContain('class="shrink-0 size-6 text-gcego-danger ml-1"')
        ->toContain('role="img"')
        ->toContain('aria-label="Edit"')
        ->toContain('stroke-width="1.5"');
});

it('renders the decorative attribute as hidden from assistive technology', function (): void {
    expect(Blade::render('<x-gcego-icon name="actions.save" label="Save" decorative />'))->toContain('aria-hidden="true"')->not->toContain('aria-label');
});

it('refuses event handler attributes on x-gcego-icon', function (): void {
    expect(fn () => Blade::render('<x-gcego-icon name="actions.save" onclick="alert(1)" />'))->toThrow(function (ViewException $exception): void {
        expect(rootCause($exception))->toBeInstanceOf(UnsafeSvgException::class);
    });
});

it('gives icon-only buttons an accessible name and a decorative icon', function (): void {
    $html = Blade::render('<x-gcego-icon-button icon="actions.delete" label="Delete <subject>" tone="danger" wire:click="delete" />');

    expect($html)->toContain('<button type="button" title="Delete &lt;subject&gt;"')
        ->toContain('wire:click="delete"')
        ->toContain('<span class="sr-only">Delete &lt;subject&gt;</span>')
        ->toContain('aria-hidden="true"')
        ->toContain('data-gcego-icon="heroicon:trash"');
});

it('renders an icon link when href is given', function (): void {
    expect(Blade::render('<x-gcego-icon-button icon="navigation.external" label="Open docs" href="https://example.com" />'))->toContain('<a href="https://example.com" title="Open docs"');
});

it('refuses icon-only buttons without a label', function (): void {
    expect(fn () => Blade::render('<x-gcego-icon-button icon="actions.delete" />'))->toThrow(function (ViewException $exception): void {
        expect(rootCause($exception))->toBeInstanceOf(InvalidConfigurationException::class)
            ->and($exception->getMessage())->toContain('needs a label');
    });
});

it('re-renders icons inside Livewire components', function (): void {
    Livewire::test(IconToggle::class)
        ->assertSeeHtml('data-gcego-icon="heroicon:eye"')
        ->assertSeeHtml('wire:key="eye"')
        ->call('toggle')
        ->assertSeeHtml('data-gcego-icon="heroicon:eye-slash"')
        ->assertDontSeeHtml('data-gcego-icon="heroicon:eye"');
});
