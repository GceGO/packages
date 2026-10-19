<?php

declare(strict_types=1);

namespace Gcego\Forms;

use Gcego\Forms\Building\FormBuilder;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Forms\Contracts\FormComponent;
use Gcego\Forms\Schema\SchemaCompiler;
use Throwable;

/**
 * A declarative form, and the "form" part of a blueprint:
 *
 *     new Form(
 *         new TextInput('name', 'Subject Name'),
 *         new TextInput('code', 'Subject Code'),
 *         new Select('category', 'Category', new Options([...])),
 *         new Toggle('is_published', 'Published'),
 *     )
 *
 * A Form is a definition only: constructing it never queries, validates,
 * renders or persists anything. In a blueprint with a Source, gcego/actions'
 * Create and Edit use it to validate and prepare their input.
 */
final class Form extends Component implements BlueprintPart
{
    public const string SubmitLabel = 'submit_label';

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Form);
    }

    public function formName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : 'form';
    }

    public static function builderClass(): string
    {
        return FormBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('forms.form')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), default: 'form', rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) === 1, ruleDescription: 'a lowercase name'),
                new PropertyDefinition(self::SubmitLabel, Type::string()),
            )
            ->withAllowedChildren([FormComponent::class])
            ->withConstraint(static function (ComponentInterface $form): ?string {
                if (! $form instanceof self) {
                    return null;
                }

                try {
                    (new SchemaCompiler)->compile($form);
                } catch (Throwable $exception) {
                    return $exception->getMessage();
                }

                return null;
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }
}
