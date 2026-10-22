<?php

declare(strict_types=1);

namespace Gcego\Security\Integration;

use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Rendering\Contracts\RenderAuthorizer;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Illuminate\Contracts\Container\Container;

/**
 * gcego/rendering's RenderAuthorizer, backed by definition requirements.
 *
 * The runtime calls it before loading a definition for every public render
 * and on every Livewire request of <livewire:gcego-blueprint>, so access is
 * re-checked on each interaction with the current user and tenant. Services
 * are resolved per call, never captured, because this object lives as long
 * as the application.
 */
final readonly class SecurityRenderAuthorizer implements RenderAuthorizer
{
    public function __construct(private Container $container) {}

    public function authorize(DefinitionDescriptor $descriptor): void
    {
        $this->container->make(DefinitionAuthorizer::class)->identity($descriptor->key())->authorize();
    }
}
