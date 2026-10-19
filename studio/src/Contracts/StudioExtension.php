<?php

declare(strict_types=1);

namespace Gcego\Studio\Contracts;

use Gcego\Studio\Extensions\StudioExtensionContext;

/**
 * Trusted package code that plugs into Studio, registered in configuration
 * (gcego-studio.extensions) or with Studio::extend() while booting — never
 * from request input. Through the context it may add health checks,
 * installation steps, inspector sections and panel extensions
 * (gcego/panels PanelExtension). Extensions run in (priority, id) order;
 * a duplicate id is refused.
 */
interface StudioExtension
{
    /** A stable identifier such as "acme/reports". */
    public function id(): string;

    /** Lower runs first (default 100). */
    public function priority(): int;

    public function register(StudioExtensionContext $context): void;
}
