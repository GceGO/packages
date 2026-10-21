<?php

declare(strict_types=1);

namespace Gcego\Modules\Definitions;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Operation;
use Gcego\Pages\Records\RecordKey;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\PermissionName;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * What a Resource and a Relation share: the records they manage, the
 * Table, Form and Infolist they coordinate, their actions, operations,
 * labels and authorization. The definitions themselves stay with
 * gcego/tables, gcego/forms, gcego/infolists and gcego/actions.
 */
abstract class RecordSet extends Component
{
    /** Eloquent model class. */
    public const string Model = 'model';

    /** URL segment (default: the name). */
    public const string Slug = 'slug';

    public const string PluralLabel = 'plural_label';

    public const string Icon = 'icon';

    /** Record attribute used as its label in titles and breadcrumbs. */
    public const string RecordTitle = 'record_title';

    /** Shape of {record}: id, uuid, ulid, slug or key. */
    public const string RecordKey = 'record_key';

    /** Restrict (or, for create and edit, enable) operations: ['index', 'view']. */
    public const string Operations = 'operations';

    /** gcego/security permissions required per operation: ['index' => 'subjects.view', 'create' => 'subjects.create']. */
    public const string Permissions = 'permissions';

    public const string SlugPattern = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Actions the forms submit; they belong to the create and edit pages. */
    public const array FormActions = ['create', 'edit'];

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) && $label !== '' ? $label : Str::headline(Str::singular($this->recordSetName()));
    }

    public function pluralLabel(): string
    {
        $label = $this->get(self::PluralLabel);

        return is_string($label) && $label !== '' ? $label : Str::plural($this->label());
    }

    /**
     * The name used in page names and URLs (lowercase, "-" separated).
     */
    abstract public function recordSetName(): string;

    /**
     * @return class-string|null
     */
    public function model(): ?string
    {
        $model = $this->get(self::Model);

        return is_string($model) && class_exists($model) ? $model : null;
    }

    public function slug(): string
    {
        $slug = $this->get(self::Slug);

        return is_string($slug) && $slug !== '' ? $slug : $this->recordSetName();
    }

    public function icon(): ?string
    {
        return $this->stringProperty(self::Icon);
    }

    public function recordTitle(): ?string
    {
        return $this->stringProperty(self::RecordTitle);
    }

    public function recordKey(): ?string
    {
        return $this->stringProperty(self::RecordKey);
    }

    public function table(): ?Table
    {
        return $this->firstChild(Table::class);
    }

    public function form(): ?Form
    {
        return $this->firstChild(Form::class);
    }

    public function infolist(): ?Infolist
    {
        return $this->firstChild(Infolist::class);
    }

    /**
     * Every declared action and action group, the form actions included.
     *
     * @return list<Action|ActionGroup>
     */
    public function actions(): array
    {
        $actions = [];

        foreach ($this->children() as $child) {
            if ($child instanceof Action || $child instanceof ActionGroup) {
                $actions[] = $child;
            }
        }

        return $actions;
    }

    /**
     * The declared action named $name (top level), if any.
     */
    public function action(string $name): ?Action
    {
        foreach ($this->actions() as $action) {
            if ($action instanceof Action && $action->actionName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Actions other than the form actions (Create and Edit), for the list and record pages.
     *
     * @return list<Action|ActionGroup>
     */
    public function recordActions(): array
    {
        return array_values(array_filter($this->actions(), static fn (Action|ActionGroup $action): bool => ! $action instanceof Action || ! in_array($action->actionName(), self::FormActions, true)));
    }

    /**
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    /**
     * Explicitly listed operations, or null when they follow the declarations.
     *
     * @return list<Operation>|null
     */
    public function listedOperations(): ?array
    {
        $listed = $this->get(self::Operations);

        if (! is_array($listed)) {
            return null;
        }

        return array_values(array_filter(array_map(static fn (mixed $value): ?Operation => is_string($value) ? Operation::tryFrom($value) : null, $listed)));
    }

    /**
     * The operations this definition really supports: each needs its Table,
     * Form or Infolist, create and edit also need their action (or a listing
     * in Operations / $defaults), and Operations restricts the result.
     *
     * @param  list<Operation>  $defaults  gcego-modules.resources.default_operations
     * @return list<Operation>
     */
    public function operations(array $defaults = []): array
    {
        $listed = $this->listedOperations();
        $operations = [];

        foreach (Operation::cases() as $operation) {
            $declared = match ($operation) {
                Operation::Index => $this->table() !== null,
                Operation::View => $this->infolist() !== null,
                Operation::Create, Operation::Edit => $this->form() !== null && ($this->action($operation->value) !== null || in_array($operation, $listed ?? [], true) || in_array($operation, $defaults, true)),
            };

            if ($declared && ($listed === null || in_array($operation, $listed, true))) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    /**
     * @return array<string, string> operation => permission
     */
    public function permissions(): array
    {
        $permissions = $this->get(self::Permissions);
        $map = [];

        foreach (is_array($permissions) ? $permissions : [] as $operation => $permission) {
            if (is_string($operation) && is_string($permission)) {
                $map[$operation] = $permission;
            }
        }

        return $map;
    }

    /**
     * Definition problems beyond property types.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach ([Table::class, Form::class, Infolist::class] as $class) {
            if (count($this->childrenOf($class)) > 1) {
                $problems[] = sprintf('declare at most one %s.', class_basename($class));
            }
        }

        foreach ($this->listedOperations() ?? [] as $operation) {
            if (! $this->hasRole($operation)) {
                $problems[] = sprintf('the "%s" operation needs a %s.', $operation->value, ucfirst($operation->requiredRole()));
            }
        }

        foreach ($this->permissions() as $operation => $permission) {
            if (Operation::tryFrom($operation) === null) {
                $problems[] = sprintf('Permissions: "%s" is not an operation (use %s).', mb_substr($operation, 0, 40), implode(', ', Operation::values()));
            }

            try {
                PermissionName::assert($permission);
            } catch (Throwable $exception) {
                $problems[] = 'Permissions: '.$exception->getMessage();
            }
        }

        $names = [];

        foreach ($this->actions() as $action) {
            $name = $action instanceof Action ? $action->actionName() : null;

            if ($name !== null && isset($names[$name])) {
                $problems[] = sprintf('the action "%s" is declared twice.', $name);
            }

            $names[(string) $name] = true;
        }

        return $problems;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;

        return parent::defineSchema()
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), rule: $text(120), ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::PluralLabel, Type::string(), rule: $text(120), ruleDescription: 'a label of at most 120 characters'),
                new PropertyDefinition(self::Model, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && class_exists($value) && is_subclass_of($value, Model::class), ruleDescription: 'an Eloquent model class'),
                new PropertyDefinition(self::Slug, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 60 && preg_match(self::SlugPattern, $value) === 1, ruleDescription: 'a lowercase URL segment such as "subjects"'),
                new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
                new PropertyDefinition(self::RecordTitle, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z_][a-z0-9_]{0,63}$/i', $value) === 1, ruleDescription: 'an attribute name such as "name"'),
                new PropertyDefinition(self::RecordKey, Type::string(), allowedValues: array_keys(RecordKey::Patterns)),
                new PropertyDefinition(self::Operations, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! is_string($entry) || Operation::tryFrom($entry) === null) === [], ruleDescription: 'a list of '.implode(', ', Operation::values())),
                new PropertyDefinition(self::Permissions, Type::array()),
            )
            ->withConstraint(static fn (ComponentInterface $component): array => $component instanceof self ? $component->problems() : []);
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hasRole(Operation $operation): bool
    {
        return match ($operation) {
            Operation::Index => $this->table() !== null,
            Operation::Create, Operation::Edit => $this->form() !== null,
            Operation::View => $this->infolist() !== null,
        };
    }

    /**
     * @template T of ComponentInterface
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function firstChild(string $class): ?ComponentInterface
    {
        return $this->childrenOf($class)[0] ?? null;
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
}
