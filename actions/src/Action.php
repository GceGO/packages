<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Building\ActionBuilder;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Support\Absent;
use Gcego\Components\Type;
use Gcego\Luminaries\IconName;
use Gcego\Security\Permissions\PermissionName;

/**
 * A declarative action: what it is, never what it does when constructed.
 *
 *     new Action('publish', 'Publish', new Handler(PublishSubject::class), new Property(Action::Ability, 'publish'))
 *
 *     new Action(
 *         new Property(Action::Name, 'publish'),
 *         new Property(Action::Label, 'Publish'),
 *         new Handler(fn (ActionContext $context) => $context->record->update(['is_published' => true])),
 *     )
 *
 * Constructing, validating, normalizing or registering an action never
 * authorizes, queries or executes anything. Execution happens only through
 * ActionManager (or the Livewire ActionHost), which resolves the action from
 * its trusted, registered blueprint.
 */
class Action extends Component implements BlueprintPart
{
    public const string Icon = 'icon';

    /** Where it appears: one placement or a list (ActionPlacement constants). */
    public const string Placement = 'placement';

    /** primary, secondary or destructive (ActionIntent). */
    public const string Intent = 'intent';

    /** The Gate ability / policy method checked before execution. */
    public const string Ability = 'ability';

    /** Whether a confirmed, single-use token is required before execution. */
    public const string Confirmation = 'confirmation';

    /** true: needs a record; false: never takes one; null: optional. */
    public const string Record = 'record';

    /** Hidden actions are not rendered and cannot be executed. */
    public const string Hidden = 'hidden';

    /** Disabled actions are rendered disabled and cannot be executed. */
    public const string Disabled = 'disabled';

    /** Run inside a database transaction (default: gcego-actions.transactions.enabled). */
    public const string Transaction = 'transaction';

    /** Require an idempotency key; repeated keys are rejected as duplicates. */
    public const string Idempotent = 'idempotent';

    public const string SuccessMessage = 'success_message';

    public const string FailureMessage = 'failure_message';

    /** Position among actions of the same placement (lower first). */
    public const string Order = 'order';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[_-][a-z0-9]+)*$/';

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Action);
    }

    public function actionName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : ucfirst(str_replace(['-', '_'], ' ', $this->actionName()));
    }

    public function ability(): ?string
    {
        $ability = $this->get(self::Ability);

        return is_string($ability) ? $ability : null;
    }

    public function intent(): ActionIntent
    {
        $intent = $this->get(self::Intent);

        return is_string($intent) ? (ActionIntent::tryFrom($intent) ?? ActionIntent::Secondary) : ActionIntent::Secondary;
    }

    /**
     * @return list<string>
     */
    public function placements(): array
    {
        $placement = $this->get(self::Placement);

        return array_values(array_filter(is_array($placement) ? $placement : [$placement], is_string(...)));
    }

    public function requiresRecord(): ?bool
    {
        $record = $this->get(self::Record);

        return is_bool($record) ? $record : null;
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    public function isDisabled(): bool
    {
        return $this->get(self::Disabled) === true;
    }

    public function order(): int
    {
        $order = $this->get(self::Order);

        return is_int($order) ? $order : 0;
    }

    /**
     * The explicit Confirmation property, or null to use the configured default for this type.
     */
    public function confirmationSetting(): ?bool
    {
        $confirmation = $this->get(self::Confirmation);

        return is_bool($confirmation) ? $confirmation : null;
    }

    public function handler(): ?Handler
    {
        $handler = $this->configurations()->first(Handler::class);

        if ($handler instanceof Handler) {
            return $handler;
        }

        $default = static::defaultHandler();

        return $default === null ? null : new Handler($default);
    }

    /**
     * The configuration key of the confirmation default (gcego-actions.confirmation.*), if any.
     */
    public static function confirmationKey(): ?string
    {
        return null;
    }

    /**
     * Whether execution must obtain validated input (Create and Edit).
     */
    public static function requiresInput(): bool
    {
        return false;
    }

    /**
     * Whether the record is resolved among soft-deleted rows: 'only', 'with' or null.
     */
    public static function trashed(): ?string
    {
        return null;
    }

    /**
     * @return class-string<ActionHandler>|null
     */
    protected static function defaultHandler(): ?string
    {
        return null;
    }

    /**
     * Defaults of the type: name, label, icon, ability, intent, placement, record, confirmation.
     *
     * @return array<string, scalar|list<string>|null>
     */
    protected static function typeDefaults(): array
    {
        return ['intent' => ActionIntent::Secondary->value, 'order' => 0];
    }

    public static function builderClass(): string
    {
        return ActionBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $defaults = static::typeDefaults();
        $default = static fn (string $key): mixed => array_key_exists($key, $defaults) ? $defaults[$key] : Absent::Value;
        $name = $default(self::Name);

        return parent::defineSchema()
            ->withType(static::class === self::class ? 'action' : 'action.'.strtolower(class_basename(static::class)))
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: $name === Absent::Value, default: $name, rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase action name such as "publish" or "send-report"'),
                new PropertyDefinition(self::Label, Type::string(), default: $default(self::Label)),
                new PropertyDefinition(self::Icon, Type::string(), default: $default(self::Icon), rule: IconName::accepts(...), ruleDescription: 'an icon identifier'),
                new PropertyDefinition(self::Placement, [Type::string(), Type::list()], default: $default(self::Placement), rule: static fn (mixed $value): bool => array_filter(is_array($value) ? $value : [$value], static fn (mixed $item): bool => ! is_string($item) || preg_match(ActionPlacement::Pattern, $item) !== 1) === [], ruleDescription: 'a placement or a list of placements'),
                new PropertyDefinition(self::Intent, Type::string(), default: $default(self::Intent), allowedValues: array_map(static fn (ActionIntent $intent): string => $intent->value, ActionIntent::cases())),
                new PropertyDefinition(self::Ability, Type::string(), default: $default(self::Ability), rule: static fn (mixed $value): bool => is_string($value) && PermissionName::isValid($value), ruleDescription: 'an ability name'),
                new PropertyDefinition(self::Confirmation, Type::bool(), nullable: true, default: $default(self::Confirmation)),
                new PropertyDefinition(self::Record, Type::bool(), nullable: true, default: $default(self::Record)),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
                new PropertyDefinition(self::Disabled, Type::bool(), default: false),
                new PropertyDefinition(self::Transaction, Type::bool(), nullable: true, default: $default(self::Transaction)),
                new PropertyDefinition(self::Idempotent, Type::bool(), default: false),
                new PropertyDefinition(self::SuccessMessage, Type::string(), default: $default(self::SuccessMessage)),
                new PropertyDefinition(self::FailureMessage, Type::string()),
                new PropertyDefinition(self::Order, Type::int(), default: $default(self::Order)),
            )
            ->withoutProperties(self::Id)
            ->withoutChildren();
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
