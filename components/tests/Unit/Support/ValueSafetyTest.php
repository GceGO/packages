<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Exceptions\InvalidPropertyException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Support\ValueDescriber;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Symfony\Component\Process\Process;

it('detaches nested array references before storing properties', function (): void {
    $value = 'original';
    $nested = ['value' => &$value];
    $component = new Component(new Property('options', ['nested' => &$nested]));
    $component->assertValid();

    $value = new stdClass;
    $nested['other'] = 'changed';
    $read = $component->get('options');
    $read['nested']['value'] = 'modified';

    expect($component->get('options'))->toBe(['nested' => ['value' => 'original']])
        ->and($component->normalize()['properties']['options'])->toBe(['nested' => ['value' => 'original']]);
});

it('detaches references in defaults and allowed values', function (): void {
    $value = 'original';
    $allowed = 'small';
    $definition = new PropertyDefinition('options', Type::array(), default: ['value' => &$value]);
    $size = new PropertyDefinition('size', Type::string(), allowedValues: [&$allowed]);
    $schema = new ComponentDefinition('test', [$definition, $size]);

    $value = new stdClass;
    $allowed = 'large';
    $read = $schema->defaults();
    $read['options']['value'] = 'modified';

    expect($definition->default)->toBe(['value' => 'original'])
        ->and($schema->defaults())->toBe(['options' => ['value' => 'original']])
        ->and($size->allowsValue('small'))->toBeTrue()
        ->and($size->allowsValue('large'))->toBeFalse();
});

it('rejects trailing newlines in schema and property identifiers', function (string $suffix): void {
    expect(fn () => new Property('key'.$suffix, true))->toThrow(InvalidPropertyException::class)
        ->and(fn () => new PropertyDefinition('key'.$suffix, Type::string()))->toThrow(InvalidConfigurationException::class)
        ->and(fn () => new ComponentDefinition('type'.$suffix))->toThrow(InvalidConfigurationException::class)
        ->and((new Component(new Property(Component::Id, 'identifier'.$suffix)))->validate()->isValid())->toBeFalse();
})->with(["\n", "\r\n", "\r"]);

it('uses fallback path segments for names containing a newline', function (): void {
    $component = new Component(new ChildComponent("name\n"), new ChildComponent('name'));

    expect(array_map(fn ($node): string => (string) $node->path, $component->tree()->depthFirst()))
        ->toBe(['#0', '#0.#0', '#0.name']);
});

it('enforces the property key length limit at the actual end of the string', function (): void {
    expect((new Property(str_repeat('a', 128)))->key)->toHaveLength(128)
        ->and(fn () => new Property(str_repeat('a', 129)))->toThrow(InvalidPropertyException::class)
        ->and(fn () => new Property(str_repeat('a', 128)."\n"))->toThrow(InvalidPropertyException::class);
});

it('describes unicode and malformed strings without requiring mbstring', function (): void {
    expect(ValueDescriber::describe(str_repeat('é', 40)))->toBe('string "'.str_repeat('é', 40).'"')
        ->and(ValueDescriber::describe(str_repeat('é', 41)))->toBe('string "'.str_repeat('é', 40).'..."')
        ->and(ValueDescriber::describe("\xff".str_repeat('x', 50)))->toBe('string "'."\xff".str_repeat('x', 39).'..."');

    $file = dirname(__DIR__, 3).'/src/Support/ValueDescriber.php';
    $process = new Process([
        PHP_BINARY, '-n', '-d', 'disable_functions=mb_strlen,mb_substr', '-r',
        'require '.var_export($file, true).'; echo \\Gcego\\Components\\Support\\ValueDescriber::describe(str_repeat("é", 41));',
    ]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toBe('string "'.str_repeat('é', 40).'..."');
});

it('detaches references from schema type and constraint lists', function (): void {
    $type = Type::string();
    $constraint = static fn (): null => null;
    $property = new PropertyDefinition('value', [&$type]);
    $schema = new ComponentDefinition('example', [$property], constraints: [&$constraint]);

    $type = Type::int();
    $constraint = static fn (): string => 'changed';
    $constraints = $schema->constraints();
    $constraints[0] = static fn (): string => 'changed through accessor';

    expect($property->acceptsType('original'))->toBeTrue()
        ->and($property->acceptsType(1))->toBeFalse()
        ->and($schema->constraints()[0](new Component))->toBeNull();
});
