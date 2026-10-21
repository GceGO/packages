<?php

declare(strict_types=1);

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Illuminate\Container\Container;

abstract class AbstractComponentForBuilderSafety extends Component {}

abstract class AbstractBuilderForBuilderSafety extends ComponentBuilder {}

class UnrelatedBuilderForSafety
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }
}

function componentWithBuilderForSafety(string $builder): Component
{
    $component = new class extends Component
    {
        public static string $builder;

        public static function builderClass(): string
        {
            return self::$builder;
        }
    };
    $component::$builder = $builder;

    return $component;
}

it('rejects invalid component classes before constructing a builder', function (string $class): void {
    expect(fn () => Components::define($class))->toThrow(InvalidBuilderException::class);
})->with([stdClass::class, 'MissingComponentClassForSafety', AbstractComponentForBuilderSafety::class]);

it('rejects unrelated builders before their constructors are invoked', function (): void {
    UnrelatedBuilderForSafety::$constructions = 0;
    $component = componentWithBuilderForSafety(UnrelatedBuilderForSafety::class);

    expect(fn () => $component::define())->toThrow(InvalidBuilderException::class, 'must be a concrete')
        ->and(UnrelatedBuilderForSafety::$constructions)->toBe(0);
});

it('rejects nonexistent and abstract builders with a package exception', function (string $builder): void {
    $component = componentWithBuilderForSafety($builder);

    expect(fn () => $component::define())->toThrow(InvalidBuilderException::class, 'must be a concrete');
})->with(['MissingBuilderClassForSafety', AbstractBuilderForBuilderSafety::class]);

it('rejects container builder bindings for another component', function (): void {
    Container::getInstance()->bind(ComponentBuilder::class, static fn (): ComponentBuilder => new ComponentBuilder(ChildComponent::class));

    expect(fn () => Components::define(ExampleComponent::class))->toThrow(InvalidBuilderException::class, 'for that component');
});

it('rejects custom constructor adapters that produce a different component class', function (): void {
    $builder = new class(ExampleComponent::class) extends ComponentBuilder
    {
        protected function instantiate(array $items): Component
        {
            return new Component;
        }
    };

    expect(fn () => $builder->build())->toThrow(InvalidBuilderException::class, 'The built component must be an instance of');
});

it('checks the result of overridden builder build methods', function (): void {
    Container::getInstance()->bind(ComponentBuilder::class, static fn (): ComponentBuilder => new class(ExampleComponent::class) extends ComponentBuilder
    {
        public function build(): Component
        {
            return new Component;
        }
    });

    expect(fn () => Components::make(ExampleComponent::class))->toThrow(InvalidBuilderException::class, 'The built component must be an instance of');
});

it('rejects recursive builder item arrays without exhausting the stack', function (): void {
    $items = [];
    $items[] = &$items;

    expect(fn () => Components::define(Component::class)->with($items))
        ->toThrow(InvalidBuilderException::class, 'maximum nesting depth of 32');
});

it('accepts the supported nesting limit and rejects deeper item lists', function (): void {
    $child = new ChildComponent('nested');
    $items = $child;

    for ($depth = 0; $depth < 32; $depth++) {
        $items = [$items];
    }

    expect(Components::define(Component::class)->with($items)->build()->children()->first())->toBe($child)
        ->and(fn () => Components::define(Component::class)->with([$items]))
        ->toThrow(InvalidBuilderException::class, 'maximum nesting depth of 32');
});

it('streams iterable items without losing repeated keys or requiring array-compatible keys', function (): void {
    $first = new ChildComponent('first');
    $second = new ChildComponent('second');
    $third = new ChildComponent('third');
    $items = (function () use ($first, $second, $third): Generator {
        yield 'same' => $first;
        yield 'same' => $second;
        yield new stdClass => $third;
    })();

    expect(Components::define(Component::class)->schema($items)->build()->children()->all())
        ->toBe([$first, $second, $third]);
});
