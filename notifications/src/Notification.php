<?php

declare(strict_types=1);

namespace Gcego\Notifications;

use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Notifications\Builders\NotificationBuilder;
use Gcego\Notifications\Definitions\NotificationAction;
use Gcego\Notifications\Definitions\NotificationCategory;
use Gcego\Notifications\Definitions\NotificationPriority;
use Gcego\Notifications\Definitions\NotificationType;
use Gcego\Notifications\Dispatching\DeliveryOptions;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Tables\Support\Icons;
use Illuminate\Database\Eloquent\Model;

/**
 * A notification: what it says, how it looks and where it goes.
 *
 *     Notification::success('Subject created successfully.');              // a toast, now
 *
 *     Notification::make()
 *         ->title('New Institution Request')
 *         ->body('An institution registration requires review.')
 *         ->database()
 *         ->sendTo($administrator);                                         // the inbox
 *
 *     new Notification(
 *         new Property(Notification::Title, 'Operation Completed'),
 *         new Property(Notification::Type, 'success'),
 *     )
 *
 * An immutable gcego/components definition: constructing one never
 * queries, renders or delivers. Text is plain (always escaped); links are
 * NotificationAction children with checked URLs; metadata holds scalars
 * only. Delivery goes through NotificationManager and Laravel Notifications.
 */
class Notification extends Component
{
    public const string Title = 'title';

    public const string Body = 'body';

    /** success, error, warning, info or neutral (NotificationType). */
    public const string Type = 'type';

    /** A registered category id (default "general"). */
    public const string Category = 'category';

    /** low, normal, high or critical (NotificationPriority). */
    public const string Priority = 'priority';

    public const string Icon = 'icon';

    /** toast, database, broadcast and/or mail. */
    public const string Channels = 'channels';

    /** Scalar values kept with the notification (record references, not record contents). */
    public const string Metadata = 'metadata';

    /** Title and body are translation keys, translated in the reader's locale. */
    public const string Translate = 'translate';

    /** Scalar translation parameters (escaped when shown). */
    public const string Parameters = 'parameters';

    /** Styling slots of toasts, the bell, the center and the inbox. */
    public const string ToastWrapperSlot = 'toast_wrapper';

    public const string ToastSlot = 'toast';

    public const string ToastIconSlot = 'toast_icon';

    public const string ToastTitleSlot = 'toast_title';

    public const string ToastBodySlot = 'toast_body';

    public const string ToastActionsSlot = 'toast_actions';

    public const string ToastDismissSlot = 'toast_dismiss';

    public const string BellSlot = 'bell';

    public const string BadgeSlot = 'badge';

    public const string CenterSlot = 'center';

    public const string ListSlot = 'list';

    public const string ItemSlot = 'item';

    public const string UnreadSlot = 'unread';

    public const string ReadSlot = 'read';

    public const string EmptySlot = 'empty';

    public const string LoadingSlot = 'loading';

    public const string InboxSlot = 'inbox';

    public const int MaxActions = 3;

    /**
     * @return array<string, string> Slot => description.
     */
    public static function styleSlots(): array
    {
        return [
            self::ToastWrapperSlot => 'The fixed region holding toasts.',
            self::ToastSlot => 'One toast.',
            self::ToastIconSlot => 'The toast icon.',
            self::ToastTitleSlot => 'The toast title.',
            self::ToastBodySlot => 'The toast body.',
            self::ToastActionsSlot => 'The toast links.',
            self::ToastDismissSlot => 'The toast dismiss button.',
            self::BellSlot => 'The notification bell button.',
            self::BadgeSlot => 'The unread count badge.',
            self::CenterSlot => 'The notification center panel.',
            self::ListSlot => 'A list of notifications.',
            self::ItemSlot => 'A notification in a list.',
            self::UnreadSlot => 'Added to unread notifications.',
            self::ReadSlot => 'Added to read notifications.',
            self::EmptySlot => 'The empty state.',
            self::LoadingSlot => 'The loading state.',
            self::InboxSlot => 'The inbox.',
        ];
    }

    /**
     * A fluent builder (the usual way to send).
     */
    public static function make(): NotificationBuilder
    {
        return new NotificationBuilder;
    }

    /**
     * A success toast for the current user, now.
     */
    public static function success(string $message, ?string $body = null): DispatchResult
    {
        return self::make()->title($message)->body($body)->success()->toast()->send();
    }

    public static function error(string $message, ?string $body = null): DispatchResult
    {
        return self::make()->title($message)->body($body)->error()->toast()->send();
    }

    public static function warning(string $message, ?string $body = null): DispatchResult
    {
        return self::make()->title($message)->body($body)->warning()->toast()->send();
    }

    public static function info(string $message, ?string $body = null): DispatchResult
    {
        return self::make()->title($message)->body($body)->info()->toast()->send();
    }

    /**
     * A builder from a gcego/actions result: its message and a matching type
     * (success, error for failures and conflicts, warning for denials).
     */
    public static function fromActionResult(ActionResult $result): NotificationBuilder
    {
        $type = match ($result->status) {
            ActionStatus::Success => NotificationType::Success,
            ActionStatus::Failed, ActionStatus::Conflict => NotificationType::Error,
            ActionStatus::Denied, ActionStatus::NotFound, ActionStatus::ValidationFailed => NotificationType::Warning,
            default => NotificationType::Info,
        };

        return self::make()->title($result->message !== '' ? $result->message : (string) __('gcego-notifications::messages.done'))->type($type);
    }

    /**
     * Deliver to the current user (toasts unless other channels are declared).
     */
    public function send(?DeliveryOptions $options = null): DispatchResult
    {
        return app(NotificationManager::class)->send($this, null, $options);
    }

    /**
     * Deliver to recipients (Notifiable models).
     *
     * @param  Model|iterable<Model>  $recipients
     */
    public function sendTo(Model|iterable $recipients, ?DeliveryOptions $options = null): DispatchResult
    {
        return app(NotificationManager::class)->send($this, $recipients, $options);
    }

    public function title(): string
    {
        return $this->text(self::Title) ?? '';
    }

    public function body(): ?string
    {
        return $this->text(self::Body);
    }

    public function notificationType(): NotificationType
    {
        return NotificationType::tryFrom((string) $this->text(self::Type)) ?? NotificationType::Info;
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::tryFrom((string) $this->text(self::Priority)) ?? NotificationPriority::Normal;
    }

    public function category(): string
    {
        return $this->text(self::Category) ?? Definitions\NotificationCategories::Default;
    }

    public function icon(): string
    {
        return $this->text(self::Icon) ?? $this->notificationType()->icon();
    }

    /**
     * Declared channels (null: the sender's defaults).
     *
     * @return list<string>|null
     */
    public function channels(): ?array
    {
        $channels = $this->get(self::Channels);

        return is_array($channels) ? array_values(array_unique(array_filter($channels, is_string(...)))) : null;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function metadata(): array
    {
        $metadata = $this->get(self::Metadata);

        return is_array($metadata) ? array_filter($metadata, static fn (mixed $value, mixed $key): bool => is_string($key) && (is_scalar($value) || $value === null), ARRAY_FILTER_USE_BOTH) : [];
    }

    public function translates(): bool
    {
        return $this->get(self::Translate) === true;
    }

    /**
     * @return array<string, string|int|float>
     */
    public function parameters(): array
    {
        $parameters = $this->get(self::Parameters);

        return is_array($parameters) ? array_filter($parameters, static fn (mixed $value, mixed $key): bool => is_string($key) && (is_string($value) || is_int($value) || is_float($value)), ARRAY_FILTER_USE_BOTH) : [];
    }

    /**
     * @return list<NotificationAction>
     */
    public function actions(): array
    {
        $actions = [];

        foreach ($this->children() as $child) {
            if ($child instanceof NotificationAction) {
                $actions[] = $child;
            }
        }

        return $actions;
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->title() === '' && $this->body() === null) {
            $problems[] = 'a notification needs a Title or a Body.';
        }

        if (count($this->actions()) > self::MaxActions) {
            $problems[] = sprintf('declare at most %d actions.', self::MaxActions);
        }

        $encoded = json_encode([$this->metadata(), $this->parameters()]);

        if ($encoded === false || strlen($encoded) > 4096) {
            $problems[] = 'metadata and parameters must stay small (4 KB): store record references, not records.';
        }

        return $problems;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;
        $scalars = static fn (mixed $value): bool => is_array($value) && count($value) <= 32 && array_filter($value, static fn (mixed $entry, mixed $key): bool => ! is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key) !== 1 || ! (is_scalar($entry) || $entry === null) || (is_string($entry) && mb_strlen($entry) > 500), ARRAY_FILTER_USE_BOTH) === [];

        return parent::defineSchema()
            ->withType('notifications.notification')
            ->withoutProperties(self::Label, self::Description, self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $value) === 1, ruleDescription: 'a lowercase name such as "reports.ready"'),
                new PropertyDefinition(self::Title, Type::string(), rule: $text(200), ruleDescription: 'a title of at most 200 characters'),
                new PropertyDefinition(self::Body, Type::string(), rule: $text(2000), ruleDescription: 'a body of at most 2000 characters'),
                new PropertyDefinition(self::Type, Type::string(), default: NotificationType::Info->value, allowedValues: NotificationType::values()),
                new PropertyDefinition(self::Priority, Type::string(), default: NotificationPriority::Normal->value, allowedValues: NotificationPriority::values()),
                new PropertyDefinition(self::Category, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(NotificationCategory::IdPattern, $value) === 1, ruleDescription: 'a category id such as "security"'),
                new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Channels, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $channel): bool => ! in_array($channel, NotificationsConfig::Channels, true)) === [], ruleDescription: 'a list of toast, database, broadcast and mail'),
                new PropertyDefinition(self::Metadata, Type::array(), rule: $scalars, ruleDescription: 'at most 32 scalar values keyed by names'),
                new PropertyDefinition(self::Translate, Type::bool(), default: false),
                new PropertyDefinition(self::Parameters, Type::array(), rule: $scalars, ruleDescription: 'at most 32 scalar translation parameters'),
            )
            ->withAllowedChildren([NotificationAction::class])
            ->withConstraint(static fn (ComponentInterface $notification): array => $notification instanceof self ? $notification->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Title, self::Body];
    }

    private function text(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
