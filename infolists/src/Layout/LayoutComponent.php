<?php

declare(strict_types=1);

namespace Gcego\Infolists\Layout;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\Building\LayoutBuilder;
use Gcego\Infolists\Configuration\VisibleWhen;
use Gcego\Infolists\Contracts\InfolistComponent;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Support\Str;

/**
 * Base of infolist layouts: structure only, never queries. A layout may
 * carry VisibleWhen conditions and gcego/security requirements
 * (new Section('Pay', new Permission('staff.pay.view'), ...)), checked for
 * the current user and record; a hidden layout's entries are never resolved.
 */
abstract class LayoutComponent extends Component implements InfolistComponent
{
    use Makeable;

    public const string Columns = 'columns';

    public function heading(): ?string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : null;
    }

    public function description(): ?string
    {
        $description = $this->get(self::Description);

        return is_string($description) ? $description : null;
    }

    public function columns(int $default): int
    {
        $columns = $this->get(self::Columns);

        return is_int($columns) ? $columns : $default;
    }

    /**
     * @return list<VisibleWhen>
     */
    public function conditions(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof VisibleWhen));
    }

    /**
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    abstract public function view(): string;

    public static function builderClass(): string
    {
        return LayoutBuilder::class;
    }

    /**
     * @return list<class-string<ComponentInterface>>
     */
    protected static function allowedChildren(): array
    {
        return [InfolistComponent::class];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('infolists.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id)
            ->withProperties(new PropertyDefinition(self::Columns, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'))
            ->withAllowedChildren(static::allowedChildren(), 1)
            ->withAllowedConfigurations([VisibleWhen::class, Requirement::class, Tailwind::class]);
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }
}
