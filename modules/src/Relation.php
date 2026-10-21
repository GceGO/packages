<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Definitions\RecordSet;
use Gcego\Tables\Table;
use Illuminate\Support\Str;

/**
 * A relationship manager: the records related to one record of a resource,
 * through an Eloquent relationship of its model (HasMany, BelongsToMany,
 * MorphMany, HasManyThrough...).
 *
 *     new Relation('topics', new Property(Relation::Resource, 'topics'))   // reuse the "topics" resource's Table, Form, Infolist
 *     new Relation(
 *         'topics', 'Topics',
 *         new Property(Relation::Model, Topic::class),
 *         new Table(new Text('name', 'Topic')),
 *         new Form(new TextInput('name', 'Topic')), new Create,    // created records belong to the parent
 *     )
 *     new Relation('tags', new Property(Relation::Resource, 'tags'), new Attach, new Detach)   // BelongsToMany
 *
 * Its pages live under the parent record ("/subjects/7/topics") and only
 * ever show records related to that parent: the parent is looked up in
 * scope and authorized (view; update before changing the relationship), and
 * the related records are constrained to the relationship on every query,
 * lookup and action. Related records keep their own policy checks.
 */
class Relation extends RecordSet
{
    /** A resource of the same module whose definitions (and model) the relation reuses. */
    public const string Resource = 'resource';

    public const string RelationshipPattern = '/^[a-z][A-Za-z0-9]{0,63}$/';

    /**
     * The relationship method on the parent model ("topics", "learningRecords").
     */
    public function relationship(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    /**
     * The name in page names and URLs ("learningRecords" -> "learning-records").
     */
    public function recordSetName(): string
    {
        return Str::kebab($this->relationship());
    }

    public function targetResource(): ?string
    {
        return $this->stringProperty(self::Resource);
    }

    public function problems(): array
    {
        if (preg_match(self::RelationshipPattern, $this->relationship()) !== 1) {
            return ['a relation needs the Name of the relationship method on the parent model, such as "topics".'];
        }

        $problems = parent::problems();

        if (in_array($this->recordSetName(), Resource::ReservedNames, true)) {
            $problems[] = sprintf('"%s" is reserved for page names; it cannot be a relation name.', $this->recordSetName());
        }

        if ($this->model() === null && $this->targetResource() === null) {
            $problems[] = sprintf('the relation "%s" needs a Model or the Resource whose records it shows.', $this->relationship());
        }

        return $problems;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('modules.relation')
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::RelationshipPattern, $value) === 1, ruleDescription: 'a relationship method name such as "topics"'),
                new PropertyDefinition(self::Resource, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Resource::NamePattern, $value) === 1, ruleDescription: 'a resource name of the same module'),
            )
            ->withAllowedChildren([Table::class, Form::class, Infolist::class, Action::class, ActionGroup::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
