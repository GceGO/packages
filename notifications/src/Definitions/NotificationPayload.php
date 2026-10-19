<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

use Gcego\Notifications\Notification;
use Gcego\Tables\Support\Icons;

/**
 * The normalized, storable content of a notification: plain strings,
 * checked links and scalar metadata. This is what the database keeps,
 * what broadcasts carry (minus metadata) and what views render (escaped).
 * It never contains models, recipients or secrets.
 */
final readonly class NotificationPayload
{
    public const int Version = 1;

    /**
     * @param  list<array{label: string, url: string, primary: bool, new_tab: bool}>  $actions
     * @param  array<string, string|int|float|bool|null>  $metadata
     * @param  array<string, string|int|float>  $parameters
     */
    public function __construct(
        public ?string $name,
        public string $title,
        public ?string $body,
        public NotificationType $type,
        public NotificationPriority $priority,
        public string $category,
        public string $icon,
        public array $actions = [],
        public array $metadata = [],
        public bool $translate = false,
        public array $parameters = [],
    ) {}

    public static function of(Notification $notification): self
    {
        return new self(
            name: is_string($name = $notification->get(Notification::Name)) ? $name : null,
            title: $notification->title(),
            body: $notification->body(),
            type: $notification->notificationType(),
            priority: $notification->priority(),
            category: $notification->category(),
            icon: $notification->icon(),
            actions: array_map(static fn (NotificationAction $action): array => $action->toArray(), $notification->actions()),
            metadata: $notification->metadata(),
            translate: $notification->translates(),
            parameters: $notification->parameters(),
        );
    }

    /**
     * From a stored database notification's data (null when it is not a gcego notification).
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (($data['gcego'] ?? null) !== self::Version) {
            return null;
        }

        $string = static fn (string $key): ?string => is_string($data[$key] ?? null) && $data[$key] !== '' ? $data[$key] : null;
        $actions = [];

        foreach (is_array($data['actions'] ?? null) ? $data['actions'] : [] as $action) {
            if (is_array($action) && is_string($action['label'] ?? null) && is_string($action['url'] ?? null) && SafeUrl::check($action['url'])) {
                $actions[] = ['label' => $action['label'], 'url' => $action['url'], 'primary' => ($action['primary'] ?? false) === true, 'new_tab' => ($action['new_tab'] ?? false) === true];
            }
        }

        $icon = $string('icon');

        return new self(
            name: $string('name'),
            title: $string('title') ?? '',
            body: $string('body'),
            type: NotificationType::tryFrom((string) $string('type')) ?? NotificationType::Info,
            priority: NotificationPriority::tryFrom((string) $string('priority')) ?? NotificationPriority::Normal,
            category: $string('category') ?? NotificationCategories::Default,
            icon: $icon !== null && preg_match(Icons::NamePattern, $icon) === 1 ? $icon : NotificationType::Info->icon(),
            actions: $actions,
            metadata: array_filter(is_array($data['metadata'] ?? null) ? $data['metadata'] : [], static fn (mixed $value, mixed $key): bool => is_string($key) && (is_scalar($value) || $value === null), ARRAY_FILTER_USE_BOTH),
            translate: ($data['translate'] ?? false) === true,
            parameters: array_filter(is_array($data['parameters'] ?? null) ? $data['parameters'] : [], static fn (mixed $value, mixed $key): bool => is_string($key) && (is_string($value) || is_int($value) || is_float($value)), ARRAY_FILTER_USE_BOTH),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gcego' => self::Version,
            'name' => $this->name,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
            'category' => $this->category,
            'icon' => $this->icon,
            'actions' => $this->actions,
            'metadata' => $this->metadata,
            'translate' => $this->translate,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * The title in the current locale (translated when the notification translates).
     */
    public function displayTitle(): string
    {
        return $this->translate ? (string) __($this->title, $this->parameters) : $this->title;
    }

    public function displayBody(): ?string
    {
        if ($this->body === null) {
            return null;
        }

        return $this->translate ? (string) __($this->body, $this->parameters) : $this->body;
    }

    /**
     * Whether the screen reader should be interrupted (urgent errors only).
     */
    public function isAssertive(): bool
    {
        return $this->type === NotificationType::Error && $this->priority->rank() >= NotificationPriority::High->rank();
    }
}
