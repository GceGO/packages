<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Chroma\ChromaConfig;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Exceptions\StyleConflictException;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;

it('reads the shipped configuration', function (): void {
    $config = ChromaConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-chroma.php');

    expect($config->theme)->toBe('gcego')
        ->and($config->themes)->toBe([])
        ->and($config->strict)->toBeTrue()
        ->and(ChromaConfig::fromArray(['theme' => null], 'ocean')->theme)->toBe('ocean')
        ->and(ChromaConfig::fromArray(['theme' => 'slate'], 'ocean')->theme)->toBe('slate');
});

it('rejects invalid configuration', function (array $config, string $message): void {
    expect(fn () => ChromaConfig::fromArray($config))->toThrow(InvalidThemeException::class, $message);
})->with([
    [['theme' => ''], 'gcego-chroma.theme'],
    [['themes' => 'ocean'], 'themes'],
    [['overrides' => [ExampleCard::class => 'p-4']], 'overrides'],
]);

it('looks slot definitions up by class, parent and interface', function (): void {
    $slots = new SlotRegistry;
    $slots->register(BlueprintInterface::class, [new SlotDefinition('root', 'layout.section')]);
    $slots->register(Component::class, [new SlotDefinition('root', 'layout.stack')], inherit: false);
    $slots->register(ExampleCard::class, [new SlotDefinition('root'), new SlotDefinition('header')]);

    expect(array_keys($slots->for(new Blueprint('x')) ?? []))->toBe(['root'])
        ->and(($slots->for(new Blueprint('x')) ?? [])['root']->preset)->toBe('layout.section')
        ->and(($slots->for(new Component) ?? [])['root']->preset)->toBe('layout.stack')
        ->and(array_keys($slots->for(new ExampleCard('Card')) ?? []))->toBe(['root', 'header'])
        ->and($slots->for(ComponentInterface::class))->toBeNull()
        ->and(array_keys($slots->registrations()))->toBe([BlueprintInterface::class, Component::class, ExampleCard::class]);
});

it('rejects duplicate and invalid slot registrations', function (): void {
    $slots = new SlotRegistry;
    $slots->register(ExampleCard::class, [new SlotDefinition('root')]);

    expect(fn () => $slots->register(ExampleCard::class, [new SlotDefinition('root')]))->toThrow(StyleConflictException::class)
        ->and(fn () => $slots->register(stdClass::class, []))->toThrow(InvalidSlotException::class)
        ->and(fn () => $slots->register(Blueprint::class, ['root']))->toThrow(InvalidSlotException::class) // @phpstan-ignore argument.type
        ->and(fn () => new SlotDefinition('Root'))->toThrow(InvalidSlotException::class);
});

it('collects overrides from interfaces, parents and the class itself, least specific first', function (): void {
    $slots = new SlotRegistry;
    $slots->override(ExampleCard::class, new Slot('root', 'p-8'));
    $slots->override(Component::class, new Slot('root', 'p-6'));
    $slots->override(BlueprintPart::class, new Slot('root', 'p-4'), new Slot('header', 'px-2'));

    expect(array_map(fn (Slot $slot): string => (string) $slot->classes, $slots->overridesFor(ExampleCard::class, 'root')))->toBe(['p-4', 'p-6', 'p-8'])
        ->and($slots->overridesFor(ExampleCard::class, 'body'))->toBe([]);
});
