<?php

declare(strict_types=1);

namespace Gcego\Rendering\Contracts;

use Gcego\Registry\Index\DefinitionDescriptor;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Decides whether the current user may render a definition through a public
 * entry point (renderAuthorized(), <x-gcego-rendering::render>, render
 * operations and hostedBlueprint()).
 *
 * It receives only the registry descriptor, so it runs before the definition
 * is loaded. gcego/security will replace the default Gate-based binding.
 */
interface RenderAuthorizer
{
    /**
     * @throws AuthorizationException
     */
    public function authorize(DefinitionDescriptor $descriptor): void;
}
