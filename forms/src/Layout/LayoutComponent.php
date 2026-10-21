<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

use Gcego\Components\Component;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\Building\LayoutBuilder;
use Gcego\Forms\Contracts\FormComponent;
use Illuminate\Support\Str;

/**
 * Base of form layout primitives. Layouts arrange fields and never hold
 * state; a VisibleWhen on a layout hides, skips validation of and ignores
 * submissions for every field inside it.
 */
abstract class LayoutComponent extends Component implements FormComponent
{
    use Makeable;

    public function heading(): ?string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : null;
    }

    abstract public function view(): string;

    /**
     * @return list<class-string<FormComponent>>
     */
    protected static function allowedChildren(): array
    {
        return [FormComponent::class];
    }

    public static function builderClass(): string
    {
        return LayoutBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('forms.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id)
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) === 1, ruleDescription: 'a lowercase name'))
            ->withAllowedChildren(static::allowedChildren(), min: 1);
    }
}
