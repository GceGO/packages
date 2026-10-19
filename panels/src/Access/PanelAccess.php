<?php

declare(strict_types=1);

namespace Gcego\Panels\Access;

use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Contracts\PanelAccessPolicy;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Whether a user may enter a panel. Deny by default:
 *
 * - a panel without requirements and without an AccessPolicy admits nobody;
 * - every requirement (gcego/security, for the current user and tenant) and
 *   the AccessPolicy must allow;
 * - an error while deciding denies.
 *
 * Access to one panel never implies another: each is decided on its own.
 */
final readonly class PanelAccess
{
    public function __construct(private Container $container, private PanelDefinitions $panels, private SecurityManager $security) {}

    public function allows(PanelContext $context): bool
    {
        return $this->decide($context) === PanelAccessResult::Allowed;
    }

    public function decide(PanelContext $context): PanelAccessResult
    {
        if (! $context->panel->declaresAccess) {
            return PanelAccessResult::Undeclared;
        }

        try {
            $panel = $this->panels->resolve($context->name());
            $requirements = $panel->requirements();

            if ($requirements !== [] && $this->security->check(...$requirements)->denied()) {
                return PanelAccessResult::RequirementsDenied;
            }

            $policy = $panel->accessPolicy();

            if ($policy !== null) {
                $instance = $this->container->make($policy);

                if (! $instance instanceof PanelAccessPolicy || $instance->allows($context->user, $context) !== true) {
                    return PanelAccessResult::PolicyDenied;
                }
            }

            return $requirements === [] && $policy === null ? PanelAccessResult::Undeclared : PanelAccessResult::Allowed;
        } catch (Throwable) {
            return PanelAccessResult::Failed;
        }
    }
}
