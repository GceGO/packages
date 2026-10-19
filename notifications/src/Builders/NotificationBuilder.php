<?php

declare(strict_types=1);

namespace Gcego\Notifications\Builders;

use BackedEnum;
use DateTimeInterface;
use Gcego\Components\Property;
use Gcego\Luminaries\IconName;
use Gcego\Notifications\Definitions\NotificationAction;
use Gcego\Notifications\Definitions\NotificationPriority;
use Gcego\Notifications\Definitions\NotificationType;
use Gcego\Notifications\Dispatching\DeliveryOptions;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\Notification;
use Gcego\Notifications\NotificationManager;
use Gcego\Pages\PageManager;
use Gcego\Panels\PanelManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Fluent construction of a Notification and its DeliveryOptions:
 *
 *     Notification::make()
 *         ->title('Report Ready')
 *         ->body('Your requested report is available.')
 *         ->action('View report', $url)
 *         ->database()->broadcast()
 *         ->sendTo($recipient);
 *
 * Nothing is delivered (or queried) until send() or sendTo(); build()
 * returns the validated, immutable definition.
 */
final class NotificationBuilder
{
    /** @var array<string, mixed> */
    private array $properties = [];

    /** @var list<NotificationAction> */
    private array $actions = [];

    /** @var list<string> */
    private array $channels = [];

    /** @var array<string, mixed> */
    private array $options = [];

    public function name(string $name): self
    {
        return $this->set(Notification::Name, $name);
    }

    public function title(string $title): self
    {
        return $this->set(Notification::Title, $title);
    }

    public function body(?string $body): self
    {
        return $this->set(Notification::Body, $body === '' ? null : $body);
    }

    /**
     * Title and body are translation keys, translated for each reader (and in mail, for the recipient's locale).
     *
     * @param  array<string, string|int|float>  $parameters
     */
    public function translate(array $parameters = []): self
    {
        $this->set(Notification::Translate, true);

        return $this->set(Notification::Parameters, $parameters === [] ? null : $parameters);
    }

    public function type(NotificationType|string $type): self
    {
        return $this->set(Notification::Type, $type instanceof NotificationType ? $type->value : $type);
    }

    public function success(): self
    {
        return $this->type(NotificationType::Success);
    }

    public function error(): self
    {
        return $this->type(NotificationType::Error);
    }

    public function warning(): self
    {
        return $this->type(NotificationType::Warning);
    }

    public function info(): self
    {
        return $this->type(NotificationType::Info);
    }

    public function neutral(): self
    {
        return $this->type(NotificationType::Neutral);
    }

    public function priority(NotificationPriority|string $priority): self
    {
        return $this->set(Notification::Priority, $priority instanceof NotificationPriority ? $priority->value : $priority);
    }

    public function critical(): self
    {
        return $this->priority(NotificationPriority::Critical);
    }

    public function category(string $category): self
    {
        return $this->set(Notification::Category, $category);
    }

    public function icon(string|BackedEnum $icon): self
    {
        return $this->set(Notification::Icon, IconName::of($icon));
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $metadata
     */
    public function metadata(array $metadata): self
    {
        return $this->set(Notification::Metadata, $metadata === [] ? null : $metadata);
    }

    /**
     * A link (root-relative, https or this application's URL; the destination authorizes again).
     */
    public function action(string $label, string $url, bool $primary = false, bool $newTab = false): self
    {
        $this->actions[] = new NotificationAction(
            new Property(NotificationAction::Label, $label),
            new Property(NotificationAction::Url, $url),
            new Property(NotificationAction::Primary, $primary),
            new Property(NotificationAction::NewTab, $newTab),
        );

        return $this;
    }

    /**
     * A link to a gcego/pages page, in a gcego/panels panel when one is named.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function actionToPage(string $label, string $page, string|int|null $record = null, ?string $panel = null, array $parameters = []): self
    {
        $url = $panel !== null
            ? app(PanelManager::class)->pageUrl($panel, $page, $record, $parameters)
            : app(PageManager::class)->url($page, $record, $parameters);

        if ($url === null) {
            throw InvalidNotificationException::invalid('The action "'.$label.'"', sprintf('the page "%s" has no route%s.', mb_substr($page, 0, 100), $panel === null ? '' : ' in the panel "'.$panel.'"'));
        }

        return $this->action($label, $url);
    }

    public function toast(): self
    {
        return $this->channel('toast');
    }

    public function database(): self
    {
        return $this->channel('database');
    }

    public function broadcast(): self
    {
        return $this->channel('broadcast');
    }

    public function mail(): self
    {
        return $this->channel('mail');
    }

    /**
     * @param  list<string>  $channels
     */
    public function channels(array $channels): self
    {
        foreach ($channels as $channel) {
            $this->channel($channel);
        }

        return $this;
    }

    public function tenant(string $tenant): self
    {
        return $this->option('tenant', $tenant);
    }

    /**
     * Belongs to no tenant (shown in every tenant context).
     */
    public function global(): self
    {
        return $this->option('global', true);
    }

    public function panel(string $panel): self
    {
        return $this->option('panel', $panel);
    }

    public function queue(?string $queue = null, ?string $connection = null): self
    {
        $this->option('queue', true);
        $this->option('queueName', $queue);

        return $this->option('connection', $connection);
    }

    public function now(): self
    {
        return $this->option('queue', false);
    }

    /**
     * Deliver at (Laravel queue delay; requires a queue).
     */
    public function sendAt(DateTimeInterface $at): self
    {
        $this->option('queue', true);

        return $this->option('delay', $at);
    }

    public function afterCommit(bool $afterCommit = true): self
    {
        return $this->option('afterCommit', $afterCommit);
    }

    /**
     * At most one delivery per recipient for this key (for example "invoice.42.paid").
     */
    public function idempotencyKey(string $key): self
    {
        return $this->option('idempotencyKey', $key);
    }

    public function expiresAt(DateTimeInterface $at): self
    {
        return $this->option('expiresAt', $at);
    }

    /**
     * The validated definition.
     */
    public function build(): Notification
    {
        $properties = [];

        foreach ($this->properties as $key => $value) {
            $properties[] = new Property($key, $value);
        }

        if ($this->channels !== []) {
            $properties[] = new Property(Notification::Channels, $this->channels);
        }

        return (new Notification(...$properties, ...$this->actions))->assertValid();
    }

    public function options(): DeliveryOptions
    {
        $option = fn (string $key): mixed => $this->options[$key] ?? null;

        return new DeliveryOptions(
            tenant: is_string($tenant = $option('tenant')) ? $tenant : null,
            global: $option('global') === true,
            panel: is_string($panel = $option('panel')) ? $panel : null,
            queue: is_bool($queue = $option('queue')) ? $queue : null,
            connection: is_string($connection = $option('connection')) ? $connection : null,
            queueName: is_string($name = $option('queueName')) ? $name : null,
            delay: ($delay = $option('delay')) instanceof DateTimeInterface ? $delay : null,
            afterCommit: is_bool($after = $option('afterCommit')) ? $after : null,
            idempotencyKey: is_string($key = $option('idempotencyKey')) ? $key : null,
            expiresAt: ($expires = $option('expiresAt')) instanceof DateTimeInterface ? $expires : null,
        );
    }

    /**
     * Deliver to the current user (a toast unless other channels are chosen).
     */
    public function send(): DispatchResult
    {
        return app(NotificationManager::class)->send($this->build(), null, $this->options());
    }

    /**
     * @param  Model|iterable<Model>  $recipients
     */
    public function sendTo(Model|iterable $recipients): DispatchResult
    {
        return app(NotificationManager::class)->send($this->build(), $recipients, $this->options());
    }

    private function set(string $key, mixed $value): self
    {
        if ($value === null) {
            unset($this->properties[$key]);
        } else {
            $this->properties[$key] = $value;
        }

        return $this;
    }

    private function channel(string $channel): self
    {
        if (! in_array($channel, $this->channels, true)) {
            $this->channels[] = $channel;
        }

        return $this;
    }

    private function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }
}
