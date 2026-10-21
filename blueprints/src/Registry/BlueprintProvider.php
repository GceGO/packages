<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Registry;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Registry\Contracts\DefinitionProvider;

/**
 * A class-based blueprint entrypoint, such as Modules/Academics/Subjects/Subjects.php.
 *
 * It is a gcego/registry DefinitionProvider: the entrypoint file declares the
 * class (a `return Subjects::class;` at the end is optional), and the
 * registry resolves it through the container. Use ProvidesBlueprint for
 * definitions().
 */
interface BlueprintProvider extends DefinitionProvider
{
    public function blueprint(): BlueprintInterface;
}
