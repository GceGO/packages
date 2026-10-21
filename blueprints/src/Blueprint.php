<?php

declare(strict_types=1);

namespace Gcego\Blueprints;

use Gcego\Blueprints\Contracts\BlueprintDefinition;
use Gcego\Blueprints\Contracts\BlueprintDeriver;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\ReferencesClasses;
use Gcego\Blueprints\Definitions\Actions;
use Gcego\Blueprints\Definitions\BulkActions;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Definitions\Derive;
use Gcego\Blueprints\Definitions\Description;
use Gcego\Blueprints\Definitions\Form;
use Gcego\Blueprints\Definitions\Icon;
use Gcego\Blueprints\Definitions\Infolist;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Policy;
use Gcego\Blueprints\Definitions\ReferenceCollectionDefinition;
use Gcego\Blueprints\Definitions\Relations;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Definitions\Widgets;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Metadata\BlueprintMetadata;
use Gcego\Blueprints\Support\BlueprintIdentity;
use Gcego\Blueprints\Support\Naming;
use Gcego\Blueprints\Validation\BlueprintErrorCode;
use Gcego\Blueprints\Validation\BlueprintValidationError;
use Gcego\Blueprints\Validation\BlueprintValidationResult;
use Gcego\Registry\Contracts\DerivesDefinitions;
use LogicException;

/**
 * An immutable, validated feature definition:
 *
 *     Blueprint::define(
 *         Name::make('subjects'),
 *         Title::make('Subject Management'),
 *         Model::make(Subject::class),
 *         Table::make(SubjectsTable::class),
 *         Pages::make([ListSubjects::class, CreateSubject::class]),
 *     );
 *
 * A blueprint stores references to feature classes; it never loads or runs
 * them. Engines (tables, forms, pages, ...) read the references through the
 * typed accessors and build their own objects.
 */
final readonly class Blueprint implements BlueprintInterface, DerivesDefinitions
{
    public const string Type = 'blueprint';

    /**
     * @param  array<string, BlueprintDefinition>  $definitions  Keyed by definition key, in declaration order.
     */
    private function __construct(
        private array $definitions,
    ) {}

    /**
     * Compile definitions into a blueprint.
     *
     * Every definition must be of a type in the DefinitionCatalog, each key
     * may appear once, and the required definitions (Name) must be present.
     * All problems are reported together.
     *
     * @throws InvalidBlueprintException
     */
    public static function define(BlueprintDefinition ...$definitions): self
    {
        return self::compile(array_values($definitions));
    }

    /**
     * Copy with definitions added; a definition replaces the existing one with
     * the same key in place (explicit replacement, unlike define()).
     *
     * @throws InvalidBlueprintException
     */
    public function with(BlueprintDefinition ...$definitions): self
    {
        $merged = $this->definitions;
        $added = [];

        foreach (array_values($definitions) as $definition) {
            if (array_key_exists($definition->key(), $merged) && ! isset($added[$definition->key()])) {
                $merged[$definition->key()] = $definition;
                $added[$definition->key()] = true;

                continue;
            }

            $merged[] = $definition;
        }

        return self::compile(array_values($merged));
    }

    /**
     * Copy without the given definition keys.
     *
     * @throws InvalidBlueprintException when a required definition is removed.
     */
    public function without(string ...$keys): self
    {
        return self::compile(array_values(array_diff_key($this->definitions, array_flip($keys))));
    }

    public function name(): string
    {
        $name = $this->definitions[Name::Key] ?? null;

        // define() guarantees a Name; the check only narrows the type.
        return $name instanceof Name ? $name->value() : throw new LogicException('A blueprint always has a name.');
    }

    public function title(): string
    {
        return $this->text(Title::Key) ?? Naming::humanize($this->name());
    }

    public function description(): ?string
    {
        return $this->text(Description::Key);
    }

    public function icon(): ?string
    {
        return $this->text(Icon::Key);
    }

    public function slug(): string
    {
        return Naming::slugify($this->name());
    }

    public function model(): ?string
    {
        return $this->reference(Model::Key);
    }

    public function policy(): ?string
    {
        return $this->reference(Policy::Key);
    }

    public function table(): ?string
    {
        return $this->reference(Table::Key);
    }

    public function form(): ?string
    {
        return $this->reference(Form::Key);
    }

    public function infolist(): ?string
    {
        return $this->reference(Infolist::Key);
    }

    public function pages(): array
    {
        return $this->references()[Pages::Key] ?? [];
    }

    public function actions(): array
    {
        return $this->references()[Actions::Key] ?? [];
    }

    public function bulkActions(): array
    {
        return $this->references()[BulkActions::Key] ?? [];
    }

    public function relations(): array
    {
        return $this->references()[Relations::Key] ?? [];
    }

    public function widgets(): array
    {
        return $this->references()[Widgets::Key] ?? [];
    }

    /**
     * The BlueprintDeriver classes requested with Derive::make([...]).
     *
     * @return list<class-string>
     */
    public function derivers(): array
    {
        return $this->references()[Derive::Key] ?? [];
    }

    public function identity(): BlueprintIdentity
    {
        return new BlueprintIdentity($this->name(), $this->slug(), self::class, self::Type);
    }

    public function metadata(): BlueprintMetadata
    {
        return new BlueprintMetadata(
            $this->name(),
            $this->title(),
            $this->slug(),
            $this->description(),
            ! isset($this->definitions[Title::Key]),
            $this->icon(),
        );
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function definition(string $key): ?BlueprintDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function find(string $class): ?BlueprintDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition instanceof $class) {
                return $definition;
            }
        }

        return null;
    }

    public function get(string $key): mixed
    {
        return ($this->definitions[$key] ?? null)?->value();
    }

    public function keys(): array
    {
        return array_keys($this->definitions);
    }

    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    public function references(): array
    {
        $references = [];

        foreach ($this->definitions as $key => $definition) {
            // Options definitions may reference no class at all; they then have no entry.
            if ($definition instanceof ReferencesClasses && $definition->classes() !== []) {
                $references[$key] = $definition->classes();
            }
        }

        return $references;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'slug' => $this->slug(),
            'definitions' => array_map(static fn (BlueprintDefinition $definition): mixed => $definition->value(), $this->definitions),
        ];
    }

    /**
     * The definitions of the requested derivers (gcego/registry's
     * DerivesDefinitions); nothing unless Derive::make([...]) was declared.
     *
     * @return iterable<object>
     *
     * @throws LogicException for a deriver that is not a BlueprintDeriver, or one that derives a blueprint.
     */
    public function derivedDefinitions(): iterable
    {
        foreach ($this->derivers() as $class) {
            $deriver = is_a($class, BlueprintDeriver::class, true)
                ? new $class
                : throw new LogicException(sprintf('Blueprint "%s" derives with %s, which does not implement %s.', $this->name(), $class, BlueprintDeriver::class));

            foreach ($deriver->derive($this) as $derived) {
                if ($derived instanceof BlueprintInterface) {
                    throw new LogicException(sprintf('Deriver %s of blueprint "%s" returned a blueprint; derived definitions must be of other kinds.', $class, $this->name()));
                }

                yield $derived;
            }
        }
    }

    /**
     * @param  list<BlueprintDefinition>  $definitions
     *
     * @throws InvalidBlueprintException
     */
    private static function compile(array $definitions): self
    {
        DefinitionCatalog::markInUse();
        $label = self::label($definitions);
        $compiled = [];
        $positions = [];
        $errors = [];

        foreach ($definitions as $position => $definition) {
            $key = $definition->key();
            $type = DefinitionCatalog::find($key);

            if ($type === null || $key !== $definition::definitionKey() || ! $definition instanceof $type->class) {
                $errors[] = BlueprintValidationError::of(
                    BlueprintErrorCode::UnsupportedDefinition,
                    sprintf('Argument %d (%s) declares the unsupported definition key "%s".', $position + 1, $definition::class, $key),
                    $label,
                    $key,
                    expected: $type === null ? 'one of: '.implode(', ', array_keys(DefinitionCatalog::all())) : $type->class,
                    received: $definition::class,
                    hint: $type === null ? 'Register extension definitions with DefinitionCatalog::register() before defining blueprints.' : null,
                );

                continue;
            }

            if (isset($compiled[$key])) {
                $errors[] = BlueprintValidationError::of(
                    BlueprintErrorCode::DuplicateDefinition,
                    sprintf('The "%s" definition is declared twice (arguments %d and %d); each definition may appear once.', $key, $positions[$key] + 1, $position + 1),
                    $label,
                    $key,
                    hint: self::isCollection($definition) || self::isCollection($compiled[$key])
                        ? 'Merge the lists into one ::make([...]) call.'
                        : 'Remove one of them, or use $blueprint->with() to replace a definition explicitly.',
                );

                continue;
            }

            $compiled[$key] = $definition;
            $positions[$key] = $position;
        }

        foreach (DefinitionCatalog::required() as $type) {
            if (! isset($compiled[$type->key])) {
                $errors[] = BlueprintValidationError::of(
                    BlueprintErrorCode::MissingRequiredDefinition,
                    sprintf('The required "%s" definition is missing.', $type->key),
                    $label,
                    $type->key,
                    expected: $type->class,
                    hint: $type->class === Name::class ? "Add Name::make('subjects')." : null,
                );
            }
        }

        (new BlueprintValidationResult($label, ...$errors))->throwIfInvalid();

        return new self($compiled);
    }

    /**
     * @param  list<BlueprintDefinition>  $definitions
     */
    private static function label(array $definitions): string
    {
        foreach ($definitions as $definition) {
            if ($definition instanceof Name) {
                return $definition->value();
            }
        }

        return '(unnamed)';
    }

    private static function isCollection(BlueprintDefinition $definition): bool
    {
        return $definition instanceof ReferenceCollectionDefinition;
    }

    private function text(string $key): ?string
    {
        $value = ($this->definitions[$key] ?? null)?->value();

        return is_string($value) ? $value : null;
    }

    /**
     * @return class-string|null
     */
    private function reference(string $key): ?string
    {
        return $this->references()[$key][0] ?? null;
    }
}
