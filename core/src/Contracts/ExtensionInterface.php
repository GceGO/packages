<?php

declare(strict_types=1);

namespace Gcego\Core\Contracts;

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Extensions\ExtensionDescriptor;

/**
 * A named, independently switchable framework capability.
 *
 * Extensions are registered explicitly with ExtensionManager::register() and
 * activated once, after all packages have booted, in dependency order. They
 * can be turned off with gcego.extensions.disabled.
 */
interface ExtensionInterface
{
    public function descriptor(): ExtensionDescriptor;

    public function activate(BootstrapContext $context): void;
}
