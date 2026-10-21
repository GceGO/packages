<?php

declare(strict_types=1);

namespace Gcego\Settings\Definitions;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Properties\Property;
use Gcego\Security\Contracts\Requirement;
use Gcego\Settings\Support\NamedArguments;

/**
 * The base of settings definitions: gcego/components definitions that also
 * accept named arguments (see NamedArguments). Immutable; constructing one
 * never queries, reads configuration or writes anything.
 */
abstract class SettingsNode extends Component
{
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    public function __construct(array|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null ...$items)
    {
        parent::__construct(...NamedArguments::normalize(static::class, $items, static::namedArguments(), static::keepsNull()));
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
     * gcego/security requirements declared on this definition.
     *
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    public static function validName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 64 && preg_match(self::NamePattern, $value) === 1;
    }

    /**
     * Named constructor arguments: argument name => property key.
     *
     * @return array<string, string>
     */
    protected static function namedArguments(): array
    {
        return ['name' => self::Name, 'label' => self::Label, 'description' => self::Description];
    }

    /**
     * Named arguments whose null value is meaningful.
     *
     * @return list<string>
     */
    protected static function keepsNull(): array
    {
        return [];
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
     * @template T of \Gcego\Components\Contracts\ComponentInterface
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
}
