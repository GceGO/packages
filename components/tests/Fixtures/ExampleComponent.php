<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A leaf component resembling a future table column, with shorthand support:
 *
 *     new ExampleComponent('name', 'Subject Name');
 */
final class ExampleComponent extends Component
{
    public const string Searchable = 'searchable';

    public const string PerPage = 'perPage';

    public const string Status = 'status';

    public const string Format = 'format';

    public const string Price = 'price';

    public const string Formatter = 'formatter';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.example')
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true),
                new PropertyDefinition(self::Label, Type::string(), normalizer: static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value),
                new PropertyDefinition(self::Searchable, Type::bool(), default: false),
                new PropertyDefinition(
                    self::PerPage,
                    Type::int(),
                    nullable: true,
                    default: 10,
                    rule: static fn (mixed $value): bool => is_int($value) && $value > 0,
                    ruleDescription: 'integer greater than zero',
                ),
                new PropertyDefinition(self::Status, Type::enum(Status::class), default: Status::Draft),
                new PropertyDefinition(self::Format, Type::string(), default: 'plain', allowedValues: ['plain', 'html']),
                new PropertyDefinition(self::Price, Type::instanceOf(Money::class)),
                new PropertyDefinition(self::Formatter, Type::instanceOf(Callback::class)),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
