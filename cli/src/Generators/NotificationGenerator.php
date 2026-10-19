<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Gcego\Notifications\Notification;
use Gcego\Notifications\NotificationsConfig;

/**
 * gcego:make-notification WelcomeStudent [--channels=database,mail]
 * [--type=info] [--category=general] [--body=]: a class building a
 * gcego/notifications Notification (WelcomeStudent::sendTo($user)).
 * Broadcasts go over gcego/notifications' signed private channels only.
 */
final class NotificationGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'notification';
    }

    public function description(): string
    {
        return 'A notification (gcego/notifications) with its channels';
    }

    public function requires(): array
    {
        return ['gcego/notifications'];
    }

    public function options(): array
    {
        return ['channels' => 'toast, database, broadcast, mail (comma-separated; default database)', 'type' => 'success, error, warning, info or neutral', 'category' => 'Category (default general)', 'title' => 'Title', 'body' => 'Body'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $class = $names->className($context->name);
        $channels = $context->list('channels') ?: ['database'];
        $type = $context->string('type') ?? 'info';
        $category = $context->string('category') ?? 'general';

        foreach ($channels as $channel) {
            if (! in_array($channel, NotificationsConfig::Channels, true)) {
                throw GenerationException::invalid(sprintf('Unknown channel "%s" (channels: %s).', $channel, implode(', ', NotificationsConfig::Channels)));
            }
        }

        if (! in_array($type, ['success', 'error', 'warning', 'info', 'neutral'], true)) {
            throw GenerationException::invalid('--type must be success, error, warning, info or neutral.');
        }

        if (preg_match('/^[a-z][a-z0-9_-]{0,47}$/', $category) !== 1) {
            throw GenerationException::invalid(sprintf('--category "%s" is not a valid category.', $category));
        }

        if (! in_array($category, ['general', 'system', 'account', 'security'], true)) {
            $this->next(sprintf("Register the \"%s\" category: app(NotificationManager::class)->category(new NotificationCategory('%s', '...'));", $category, $category));
        }

        $directory = $this->kit->paths->directory('notification');
        $namespace = $this->kit->namespaces->forDirectory($directory);
        $this->next(sprintf('Send it: \\%s\\%s::sendTo($user); recipients use Laravel\'s Notifiable trait.', $namespace, $class));

        return [$this->file($context, $directory.'/'.$class.'.php', 'notification', [
            'namespace' => StubValue::identifier($namespace),
            'class' => StubValue::identifier($class),
            'name' => StubValue::string($names->slug($context->name)),
            'title' => StubValue::string($context->string('title') ?? $names->title($context->name)),
            'body' => StubValue::string($context->string('body') ?? $names->title($context->name).'.'),
            'type' => StubValue::string($type),
            'category' => StubValue::string($category),
            'channels' => StubValue::code(implode(', ', array_map(static fn (string $channel): string => "'".$channel."'", array_values(array_unique($channels))))),
        ], 'Notification '.$class, $namespace, $namespace.'\\'.$class)];
    }
}
