<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

final readonly class RequestContextForIsolationReview
{
    public function __construct(public string $name) {}
}

class ScopedComponentForIsolationReview extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        $context = Container::getInstance()->make(RequestContextForIsolationReview::class);

        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(
                'tenant', Type::string(), default: $context->name,
                normalizer: static fn (string $value): string => $context->name.':'.$value,
            ))
            ->withConstraint(static fn (ComponentInterface $component): ?string => $component->get('tenant') === $context->name ? null : 'Tenant mismatch.');
    }
}

function applicationForIsolationReview(string $tenant): Application
{
    $app = new Application(dirname(__DIR__, 3));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $app->instance(RequestContextForIsolationReview::class, new RequestContextForIsolationReview($tenant));
    $app->register(ComponentServiceProvider::class);

    return $app;
}

it('isolates schema definitions and defaults between application containers', function (): void {
    applicationForIsolationReview('first');
    $first = new ScopedComponentForIsolationReview;
    $definition = $first::definition();

    expect($first::definition())->toBe($definition)
        ->and($first->get('tenant'))->toBe('first');

    applicationForIsolationReview('second');
    $second = new ScopedComponentForIsolationReview;

    expect($second::definition())->not->toBe($definition)
        ->and($second->get('tenant'))->toBe('second')
        ->and($second->normalize()['properties']['tenant'])->toBe('second:second');
});

it('releases schema closures and their request context when a Laravel scope ends', function (): void {
    $app = applicationForIsolationReview('first');
    $context = $app->make(RequestContextForIsolationReview::class);
    $contextReference = WeakReference::create($context);
    $managerReference = WeakReference::create(Components::getFacadeRoot());
    $component = new ScopedComponentForIsolationReview(new Property('tenant', 'first'));
    $component->assertValid();
    unset($context);

    $app->instance(RequestContextForIsolationReview::class, new RequestContextForIsolationReview('second'));
    $app->forgetScopedInstances();
    gc_collect_cycles();

    expect($contextReference->get())->toBeNull()
        ->and($managerReference->get())->toBeNull()
        ->and((new ScopedComponentForIsolationReview)->get('tenant'))->toBe('second')
        ->and($component->validate()->isValid())->toBeFalse();
});

it('does not reuse validation results from an earlier application', function (): void {
    applicationForIsolationReview('first');
    $component = new ScopedComponentForIsolationReview(new Property('tenant', 'first'));
    $result = $component->validate();
    expect($result->isValid())->toBeTrue()
        ->and($component->validate())->toBe($result);

    applicationForIsolationReview('second');

    expect($component->validate())->not->toBe($result)
        ->and($component->validate()->isValid())->toBeFalse();
});

it('rejects retained managers after application or request scope changes', function (string $change): void {
    $app = applicationForIsolationReview('first');
    $manager = Components::getFacadeRoot();
    $component = new Component;

    if ($change === 'application') {
        applicationForIsolationReview('second');
    } else {
        $app->forgetScopedInstances();
    }

    foreach ([
        static fn () => $manager->define(Component::class),
        static fn () => $manager->make(),
        static fn () => $manager->schema([]),
        static fn () => $manager->collect([]),
        static fn () => $manager->catalog(),
        static fn () => $manager->component('textInput', 'field'),
        static fn () => $manager->tree($component),
        static fn () => $manager->validate($component),
        static fn () => $manager->normalize($component),
        static fn () => $manager->inspect($component),
        static fn () => $manager->rememberDefinition(Component::class, static fn (): ComponentDefinition => new ComponentDefinition('wrong')),
    ] as $operation) {
        expect($operation)->toThrow(InvalidConfigurationException::class, 'inactive application or scope');
    }

    expect(Components::make()->validate()->isValid())->toBeTrue();
})->with(['application', 'request']);

it('does not expose container contents through manager debug output or serialization', function (): void {
    $app = applicationForIsolationReview('private-context');
    $app->instance('review-secret', 'private-credential-for-review');
    $manager = Components::getFacadeRoot();
    new ScopedComponentForIsolationReview;

    expect($manager->__debugInfo())->toBe(['container' => '[redacted]', 'definitionCount' => 1])
        ->and(json_encode($manager, JSON_THROW_ON_ERROR))->toBe('{}')
        ->and(fn () => serialize($manager))->toThrow(InvalidConfigurationException::class, 'cannot be serialized');
});
