<?php

declare(strict_types=1);

namespace Gcego\Settings\Integration;

use Gcego\Notifications\Notification;
use Gcego\Settings\Approvals\SettingRequest;
use Gcego\Settings\Contracts\SettingsRecipients;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\Support\Keys;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * gcego/notifications messages (category "settings", database channel,
 * in the change's tenant), sent only after the change committed:
 *
 * - sensitive settings changed: to the configured SettingsRecipients;
 * - an approval request was applied, refused, or could not be applied: to its requester.
 *
 * They name settings and scopes, never values. Delivery failures are
 * reported, never thrown into the committed change.
 */
final readonly class SettingsNotifier
{
    public const string Category = 'settings';

    public function __construct(private Container $container, private SettingsConfig $config) {}

    /**
     * @param  list<SettingDefinition>  $definitions
     */
    public function changed(array $definitions, ScopeContext $context, string $correlation): void
    {
        $sensitive = array_values(array_filter($definitions, static fn (SettingDefinition $definition): bool => $definition->sensitive));

        if (! $this->config->notifications || $sensitive === [] || $this->config->recipients === null) {
            return;
        }

        try {
            $resolver = $this->container->make($this->config->recipients);

            if (! $resolver instanceof SettingsRecipients) {
                return;
            }

            $labels = implode(', ', array_map(static fn (SettingDefinition $definition): string => $definition->label(), $sensitive));

            foreach ($resolver->recipients($sensitive, $context) as $recipient) {
                $this->send($recipient, 'sensitive', $context->tenant === null ? null : (string) $context->tenant->key, ['settings' => $labels, 'scope' => $context->target->label()], 'settings.sensitive.'.$correlation.'.'.$recipient->getMorphClass().'#'.Keys::string($recipient->getKey()), true);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function decided(SettingRequest $request, string $label): void
    {
        if (! $this->config->notifications) {
            return;
        }

        try {
            $requester = $request->requester;

            if ($requester instanceof Model) {
                $tenant = $request->tenant === null ? null : substr($request->tenant, (int) strpos($request->tenant, ':') + 1);
                $this->send($requester, 'request.'.$request->status, $tenant, ['setting' => $label], 'settings.request.'.$request->ulid.'.'.$request->status, $request->status !== SettingRequest::Applied);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function send(Model $recipient, string $message, ?string $tenant, array $parameters, string $idempotency, bool $warning): void
    {
        if (! class_exists(Notification::class) || ! method_exists($recipient, 'routeNotificationFor')) {
            return;
        }

        $builder = Notification::make()
            ->title('gcego-settings::messages.notifications.'.$message.'.title')
            ->body('gcego-settings::messages.notifications.'.$message.'.body')
            ->translate($parameters)
            ->category(self::Category)
            ->database()
            ->idempotencyKey(mb_substr(hash('sha256', $idempotency), 0, 64));
        $builder = $warning ? $builder->warning() : $builder->success();
        $builder = $tenant === null ? $builder->global() : $builder->tenant($tenant);
        $builder->sendTo($recipient);
    }
}
