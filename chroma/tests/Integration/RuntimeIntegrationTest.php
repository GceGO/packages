<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Registry\Registry;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Styling\NullStylingResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;

it('replaces the runtime null resolver', function (): void {
    expect(app(StylingResolver::class))->toBeInstanceOf(SlotResolver::class);
});

it('respects a resolver bound by the application', function (): void {
    app()->singleton(StylingResolver::class, fn () => new class implements StylingResolver
    {
        public function classes(ComponentInterface $component, string $slot, RenderContext $context): string
        {
            return 'app-resolver';
        }
    });

    expect(app(StylingResolver::class)->classes(new Component, 'root', RenderContext::root(new Component)))->toBe('app-resolver')
        ->and(app(StylingResolver::class))->not->toBeInstanceOf(NullStylingResolver::class);
});

it('styles the runtime built-in blueprint markup', function (): void {
    $html = app(Runtime::class)->render(new Blueprint('subjects', new Property(Blueprint::Description, 'Manage subjects')))->toHtml();

    expect($html)->toContain('class="flex flex-col gap-6"')
        ->and($html)->toContain('class="flex flex-wrap items-start justify-between gap-4"')
        ->and($html)->toContain('class="text-2xl font-semibold tracking-tight text-balance text-gcego-text">Subjects</h1>')
        ->and($html)->toContain('class="text-sm text-pretty text-gcego-text-muted">Manage subjects</p>');
});

it('renders component slots, nested components and instance styling', function (): void {
    $html = app(Runtime::class)->render(new Blueprint(
        'dashboard',
        new Tailwind('gap-10'),
        new ExampleCard('Enrolments', new Tailwind(new Slot(Tailwind::Body, ['grid', 'md:grid-cols-2']))),
    ))->toHtml();

    expect($html)->toContain('<section id="')
        ->and($html)->toContain('class="flex flex-col gap-10"')
        ->and($html)->toContain('<article aria-labelledby="')
        ->and($html)->toContain('class="rounded-gcego-lg border border-gcego-border bg-gcego-surface text-gcego-text shadow-gcego-card"')
        ->and($html)->toContain('class="p-6 grid md:grid-cols-2"')
        ->and($html)->not->toContain('Tailwind');
});

it('never renders a Tailwind node as a child', function (): void {
    $card = new ExampleCard('A', new Tailwind('p-2'));

    expect($card->childCount())->toBe(0)
        ->and(app(Runtime::class)->render($card)->toHtml())->not->toContain('slots');
});

it('escapes resolved classes in attributes', function (): void {
    $card = new ExampleCard('A', new Tailwind("data-[x='1']:p-4"));

    expect(app(Runtime::class)->render($card)->toHtml())->toContain('data-[x=&#039;1&#039;]:p-4');
});

it('merges with Blade attribute bags without duplicating the class attribute', function (): void {
    $classes = app(ChromaManager::class)->classes(new ExampleCard('A'), Tailwind::Body);
    $bag = new ComponentAttributeBag(['class' => 'mt-4', 'aria-label' => 'Body', 'wire:loading.class' => 'opacity-50']);

    $html = Blade::render('<div {{ $attributes->class($classes) }}></div>', ['attributes' => $bag, 'classes' => $classes]);

    expect($html)->toBe('<div class="p-6 mt-4" aria-label="Body" wire:loading.class="opacity-50"></div>')
        ->and(substr_count($html, 'class='))->toBe(2);
});

it('keeps classes stable across Livewire re-renders', function (): void {
    Gate::define('gcego-runtime.render', fn (?object $user): bool => true);
    app(Registry::class)->register(new Blueprint('dashboard', new ExampleCard('Enrolments', new Tailwind('ring-1'))));

    $classes = function (string $html): array {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        return $matches[1];
    };
    $component = Livewire::test(BlueprintHost::class, ['blueprint' => 'dashboard']);
    $first = $classes($component->html());
    $component->call('$refresh');

    expect($classes($component->html()))->toBe($first)
        ->and(implode(' ', $first))->toContain('shadow-gcego-card ring-1');
});
