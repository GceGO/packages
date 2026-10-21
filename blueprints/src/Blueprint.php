<?php

declare(strict_types=1);

namespace Gcego\Blueprints;

use Gcego\Blueprints\Composition\BlueprintComposer;
use Gcego\Blueprints\Composition\BlueprintComposition;
use Gcego\Blueprints\Composition\BlueprintPartCollection;
use Gcego\Blueprints\Composition\CompositionPolicy;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Blueprints\Exceptions\BlueprintCompositionException;
use Gcego\Blueprints\Exceptions\DuplicateBlueprintPartException;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Exceptions\MissingBlueprintPartException;
use Gcego\Blueprints\Metadata\BlueprintMetadata;
use Gcego\Blueprints\Support\BlueprintIdentity;
use Gcego\Blueprints\Support\Naming;
use Gcego\Components\Collections\ComponentCollection;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A composed feature definition: metadata plus role-classified parts.
 *
 *     new Blueprint('subjects', new SubjectsTable, new SubjectsForm);
 *
 *     new Blueprint(
 *         new Property(Blueprint::Name, 'subjects'),
 *         new Property(Blueprint::Title, 'Subject Management'),
 *         new SubjectsTable,
 *     );
 *
 * Blueprint is a gcego/morfaw Component: construction, properties,
 * copying, traversal, validation and normalization are the component
 * engine's. Blueprint adds the schema (metadata properties, BlueprintPart
 * children), role classification and part lookup.
 *
 * Constructor shorthand: one leading string is the name. Nothing else is
 * positional, so a string never means a title, slug or model.
 */
class Blueprint extends Component implements BlueprintInterface
{
    public const string Title = 'title';

    public const string Icon = 'icon';

    public const string Slug = 'slug';

    /**
     * @var array<class-string, CompositionPolicy>
     */
    private static array $policies = [];

    /**
     * The children and name the cached composition was computed for.
     * Component's final __clone() cannot reset subclass caches, so the cache
     * is keyed on the (immutable) children collection and the name instead.
     */
    private ?ComponentCollection $composedChildren = null;

    private ?string $composedName = null;

    private ?BlueprintComposition $composition = null;

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType(static::class === self::class ? 'blueprint' : static::class)
            ->withoutProperties(self::Label, self::Id)
            ->withProperties(
                new PropertyDefinition(
                    self::Name,
                    Type::string(),
                    required: true,
                    rule: Naming::isName(...),
                    ruleDescription: 'a lowercase name such as "subjects" or "academic-subjects" (letters, digits, "-" and "_"; at most 100 characters)',
                    description: 'Logical identity; registries key blueprints by it.',
                ),
                new PropertyDefinition(
                    self::Title,
                    Type::string(),
                    rule: Naming::isTitle(...),
                    ruleDescription: 'a non-empty title of at most 200 characters',
                    description: 'Display title; derived from the name when omitted.',
                ),
                new PropertyDefinition(self::Description, Type::string()),
                new PropertyDefinition(
                    self::Icon,
                    Type::string(),
                    rule: Naming::isIcon(...),
                    ruleDescription: 'an icon identifier such as "actions.edit", "lucide:book-open" or "academic-cap"',
                ),
                new PropertyDefinition(
                    self::Slug,
                    Type::string(),
                    rule: Naming::isSlug(...),
                    ruleDescription: 'a lowercase slug such as "subject-management"',
                    description: 'URL-safe identifier; derived from the name when omitted.',
                ),
            )
            ->withAllowedChildren([BlueprintPart::class])
            ->withConstraint(static fn (ComponentInterface $component): array => $component instanceof BlueprintInterface
                ? $component->composition()->messages()
                : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    /**
     * The composition rules of this class, built once.
     */
    final public static function compositionPolicy(): CompositionPolicy
    {
        return self::$policies[static::class] ??= static::definePolicy();
    }

    /**
     * Override to require or restrict roles. The base policy imposes nothing.
     */
    protected static function definePolicy(): CompositionPolicy
    {
        return new CompositionPolicy;
    }

    final public function metadata(): BlueprintMetadata
    {
        return BlueprintMetadata::of($this);
    }

    final public function title(): ?string
    {
        return $this->metadata()->title;
    }

    final public function slug(): ?string
    {
        return $this->metadata()->slug;
    }

    final public function identity(): BlueprintIdentity
    {
        $metadata = $this->metadata();

        if ($metadata->name === null || ! Naming::isName($metadata->name) || $metadata->slug === null) {
            throw InvalidBlueprintException::unnamed(static::class);
        }

        return new BlueprintIdentity($metadata->name, $metadata->slug, static::class, $this->type());
    }

    final public function composition(): BlueprintComposition
    {
        if ($this->composition === null || $this->composedChildren !== $this->children() || $this->composedName !== $this->name()) {
            $this->composition = (new BlueprintComposer)->compose($this);
            $this->composedChildren = $this->children();
            $this->composedName = $this->name();
        }

        return $this->composition;
    }

    final public function part(string $role): ?BlueprintPart
    {
        $composition = $this->composition();
        $parts = $composition->ofRole($role);

        if ($parts->isEmpty()) {
            return null;
        }

        if ($composition->role($role)?->multiple === true) {
            throw BlueprintCompositionException::multipleRole($this->displayName(), $role);
        }

        if ($parts->count() > 1) {
            throw DuplicateBlueprintPartException::forRole($this->displayName(), $role, $parts->paths());
        }

        return $parts->first();
    }

    final public function requirePart(string $role): BlueprintPart
    {
        return $this->part($role) ?? throw MissingBlueprintPartException::forRole($this->displayName(), $role);
    }

    final public function parts(?string $role = null): BlueprintPartCollection
    {
        return $role === null ? $this->composition()->parts() : $this->composition()->ofRole($role);
    }

    final public function hasPart(string $role): bool
    {
        return $this->composition()->has($role);
    }

    final public function roles(): array
    {
        return $this->composition()->roles();
    }

    /**
     * Copy with a part added.
     *
     * For a single-cardinality role the new part replaces the existing direct
     * part(s) of that role, keeping the first one's position; otherwise it is
     * appended. Other parts keep their order.
     */
    final public function withPart(BlueprintPart $part): static
    {
        $role = $part->blueprintRole();
        $single = ! $role->multiple || static::compositionPolicy()->forcesSingle($role->name);

        if (! $single) {
            return $this->withAppendedChildren($part);
        }

        $children = [];
        $replaced = false;

        foreach ($this->children() as $child) {
            if (! $child instanceof BlueprintPart || $child->blueprintRole()->name !== $role->name) {
                $children[] = $child;
            } elseif (! $replaced) {
                $children[] = $part;
                $replaced = true;
            }
        }

        if (! $replaced) {
            $children[] = $part;
        }

        return $this->withChildren(...$children);
    }

    /**
     * Copy without the direct parts of the given role(s).
     */
    final public function withoutRole(string ...$roles): static
    {
        return $this->withChildren(...array_filter(
            $this->children()->all(),
            static fn (ComponentInterface $child): bool => ! $child instanceof BlueprintPart || ! in_array($child->blueprintRole()->name, $roles, true),
        ));
    }

    private function displayName(): string
    {
        return $this->name() ?? static::class;
    }
}
