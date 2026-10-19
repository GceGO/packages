<?php

declare(strict_types=1);

namespace Gcego\Notifications\Delivery;

use Gcego\Notifications\Contracts\NotificationDeliveryPolicy;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Whether a recipient may receive a notification at all (before
 * preferences): a member of the notification's tenant (gcego/security
 * TenantAuthorizer, when gcego-notifications.security.authorize_recipients)
 * and allowed by the application's delivery policy. Errors deny.
 */
final readonly class RecipientAuthorizer
{
    public function __construct(
        private Container $container,
        private NotificationsConfig $config,
        private SecurityConfig $security,
    ) {}

    /**
     * @return string|null null when allowed, else why not ("tenant", "policy").
     */
    public function deny(Model $recipient, NotificationPayload $payload, DeliveryContext $context): ?string
    {
        if ($context->tenant !== null && $this->config->authorizeRecipients && $this->security->tenancy) {
            if (! $recipient instanceof Authenticatable) {
                return 'tenant';
            }

            try {
                $this->container->make(TenantAuthorizer::class)->verify($recipient, $context->tenant, $this->security->guard ?? 'web');
            } catch (Throwable) {
                return 'tenant';
            }
        }

        if ($this->config->deliveryPolicy !== null) {
            try {
                $policy = $this->container->make($this->config->deliveryPolicy);

                if (! $policy instanceof NotificationDeliveryPolicy || $policy->allows($recipient, $payload, $context) !== true) {
                    return 'policy';
                }
            } catch (Throwable) {
                return 'policy';
            }
        }

        return null;
    }
}
