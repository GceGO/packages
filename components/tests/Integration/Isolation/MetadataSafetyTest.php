<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Contracts\RuntimeValue;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Normalization\RuntimePlaceholder;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Illuminate\Container\Container;

class ComponentForMetadataReview extends Component
{
    public static string $runtimeClass;

    public static string $configurationClass;

    public static string $childClass;

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition('runtime', Type::instanceOf(self::$runtimeClass)))
            ->withAllowedChildren([self::$childClass])
            ->withAllowedConfigurations([self::$configurationClass]);
    }
}

it('keeps runtime values opaque even when property normalizers try to read them', function (): void {
    $value = new class implements RuntimeValue
    {
        public string $secret = 'private-runtime-state';
    };
    $called = false;
    $definition = new ComponentDefinition('review', [new PropertyDefinition(
        'runtime', Type::instanceOf($value::class), default: $value,
        normalizer: static function ($runtime) use (&$called): string {
            $called = true;

            return $runtime->secret;
        },
    )]);
    $result = Container::getInstance()->make(ComponentNormalizer::class)->normalizeProperties(['runtime' => $value], $definition);

    expect($called)->toBeFalse()
        ->and($result['runtime'])->toBeInstanceOf(RuntimePlaceholder::class)
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('private-runtime-state', __FILE__, "\u0000");
});

it('omits anonymous source locations from normalized inspected schema and error metadata', function (): void {
    $runtime = new class implements RuntimeValue {};
    $configuration = new readonly class implements ConfigurationNode
    {
        public function normalize(): array
        {
            return [];
        }
    };
    $child = new class extends Component {};
    ComponentForMetadataReview::$runtimeClass = $runtime::class;
    ComponentForMetadataReview::$configurationClass = $configuration::class;
    ComponentForMetadataReview::$childClass = $child::class;
    $component = new class(new Property('runtime', $runtime), $configuration, $child) extends ComponentForMetadataReview {};

    $normalizer = Container::getInstance()->make(ComponentNormalizer::class, ['includeSchema' => true]);
    $normalized = $normalizer->normalize($component);
    $inspection = Components::inspect($component)->toArray();
    $errors = $component->withProperty(new Property('runtime', 'wrong'))->validate()->toArray();

    foreach ([$normalized, $inspection, $errors] as $data) {
        expect(json_encode($data, JSON_THROW_ON_ERROR))->not->toContain(__FILE__, "\u0000");
    }

    expect($component->validate()->isValid())->toBeTrue()
        ->and($component::definition()->allowsChild($child))->toBeTrue()
        ->and($component::definition()->allowsConfiguration($configuration))->toBeTrue();

    try {
        $class = $component::class;
        new $class(new Property('label', 'first'), new Property('label', 'second'));
        test()->fail('Expected duplicate property exception.');
    } catch (DuplicatePropertyException $exception) {
        expect($exception->getMessage())->not->toContain(__FILE__, "\0");
    }
});

it('normalizes object defaults in direct schema exports as well as component output', function (): void {
    $runtime = new class implements RuntimeValue
    {
        public string $secret = 'private-schema-default';
    };
    $definition = new PropertyDefinition('runtime', Type::instanceOf($runtime::class), default: $runtime);
    $schema = new ComponentDefinition('review', [$definition]);
    $exported = $schema->toArray();

    expect($definition->toArray()['default'])->toBeInstanceOf(RuntimePlaceholder::class)
        ->and($exported['properties']['runtime']['default'])->toBeInstanceOf(RuntimePlaceholder::class)
        ->and(json_encode($exported, JSON_THROW_ON_ERROR))->not->toContain('private-schema-default', __FILE__, "\u0000")
        ->and(Components::normalizeValue($exported))->toBe($exported)
        ->and($definition->default)->toBe($runtime);
});

it('removes anonymous source paths from schema construction diagnostics', function (): void {
    $unrelated = new class {};

    foreach ([
        static fn () => Type::enum($unrelated::class),
        static fn () => new ComponentDefinition('review', allowedChildren: [$unrelated::class]),
        static fn () => new ComponentDefinition('review', allowedConfigurations: [$unrelated::class]),
    ] as $operation) {
        try {
            $operation();
            test()->fail('Expected invalid schema configuration.');
        } catch (InvalidConfigurationException $exception) {
            expect($exception->getMessage())->not->toContain(__FILE__, "\0");
        }
    }
});
