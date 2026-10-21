<?php

declare(strict_types=1);

namespace Gcego\Analytics\Definitions;

use Gcego\Analytics\Support\NamedArguments;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Properties\Property;
use Gcego\Security\Contracts\Requirement;

/**
 * The base of analytics definitions: immutable gcego/components that also
 * accept named arguments. Constructing one never queries anything.
 */
abstract class AnalyticsNode extends Component
{
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    /** Database identifiers of a dataset's own table (no relation paths, no expressions). */
    public const string ColumnPattern = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/';

    public function __construct(array|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null ...$items)
    {
        parent::__construct(...NamedArguments::normalize(static::class, $items, static::namedArguments()));
    }

    public function nodeName(): string
    {
        return $this->text(self::Name) ?? '';
    }

    public function label(): string
    {
        return $this->text(self::Label) ?? ucfirst(str_replace(['-', '_'], ' ', $this->nodeName()));
    }

    public function description(): ?string
    {
        return $this->text(self::Description);
    }

    /**
     * gcego/security requirements given to this definition.
     *
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    /**
     * @return array<string, string>
     */
    protected static function namedArguments(): array
    {
        return ['name' => self::Name, 'label' => self::Label, 'description' => self::Description];
    }

    protected function text(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function integer(string $key): ?int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    protected function strings(string $key): array
    {
        $value = $this->get($key);

        return is_string($value) ? [$value] : (is_array($value) ? array_values(array_filter($value, is_string(...))) : []);
    }

    /**
     * @template T of ComponentInterface
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    protected function childrenOf(string $class): array
    {
        $matches = [];

        foreach ($this->children() as $child) {
            if ($child instanceof $class) {
                $matches[] = $child;
            }
        }

        return $matches;
    }

    public static function validName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 64 && preg_match(self::NamePattern, $value) === 1;
    }

    public static function validColumn(mixed $value): bool
    {
        return is_string($value) && preg_match(self::ColumnPattern, $value) === 1;
    }

    /**
     * @param  class-string  $contract
     */
    public static function implements(mixed $value, string $contract): bool
    {
        return is_string($value) && class_exists($value) && is_subclass_of($value, $contract);
    }
}
