<?php

declare(strict_types=1);

namespace Gcego\Blueprints;

use Gcego\Blueprints\Exceptions\InvalidBlueprintPartException;

/**
 * The role a part plays in a blueprint, and how many parts may play it.
 *
 * Roles are open-ended identifiers: future packages introduce their own
 * ("reports.chart") without changes here. The constants only name the
 * conventional roles so packages agree on spelling.
 */
final readonly class BlueprintRole
{
    public const string Source = 'source';

    public const string Table = 'table';

    public const string Form = 'form';

    public const string Infolist = 'infolist';

    public const string Action = 'action';

    public const string Widget = 'widget';

    public const string Layout = 'layout';

    public const string Navigation = 'navigation';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    public function __construct(
        public string $name,
        public bool $multiple = false,
    ) {
        if (! self::isValidName($name)) {
            throw InvalidBlueprintPartException::invalidRole($name);
        }
    }

    /**
     * A role at most one part may play, such as "table".
     */
    public static function single(string $name): self
    {
        return new self($name, false);
    }

    /**
     * A role any number of parts may play, such as "action".
     */
    public static function multiple(string $name): self
    {
        return new self($name, true);
    }

    public static function isValidName(mixed $name): bool
    {
        return is_string($name) && strlen($name) <= 64 && preg_match(self::NamePattern, $name) === 1;
    }

    /**
     * @return array{name: string, multiple: bool}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'multiple' => $this->multiple];
    }
}
