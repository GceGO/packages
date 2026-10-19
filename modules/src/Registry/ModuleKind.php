<?php

declare(strict_types=1);

namespace Gcego\Modules\Registry;

use Gcego\Components\Validation\ValidationError;
use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Modules\Module;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;
use Throwable;

/**
 * The "module" definition kind of gcego/registry ("module:academics").
 * Modules are discovered from gcego-modules.discovery.paths (Module.php
 * entrypoints); the pages they derive are indexed as "page" definitions.
 *
 * Validation is the component validator's plus compilation (extensions and
 * normalization). The indexed metadata includes the page map, so contexts,
 * navigation and diagnostics read it without loading the module.
 */
final readonly class ModuleKind implements DefinitionKind
{
    public const string Id = 'module';

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return Module::class;
    }

    public function name(object $definition): string
    {
        $name = $this->module($definition)->moduleName();

        if (preg_match(Module::NamePattern, $name) !== 1) {
            throw new InvalidArgumentException('A module needs a Name such as "academics" (or a single resource).');
        }

        return $name;
    }

    public function validate(object $definition): array
    {
        $module = $this->module($definition);
        $errors = array_map(static fn (ValidationError $error): string => $error->format(), $module->validate()->errors());

        if ($errors !== []) {
            return $errors;
        }

        try {
            ModuleCompiler::instance()->compile($module);
        } catch (Throwable $exception) {
            return [$exception->getMessage()];
        }

        return [];
    }

    public function metadata(object $definition): array
    {
        $module = $this->module($definition);

        try {
            $compiled = ModuleCompiler::instance()->compile($module);
        } catch (Throwable) {
            return ['title' => $module->title()];
        }

        return [
            'title' => $compiled->title,
            'icon' => $compiled->icon,
            'slug' => $compiled->slug,
            'version' => $compiled->version,
            'navigation' => $compiled->navigation,
            'resources' => implode(',', array_map(static fn ($resource): string => $resource->name, $compiled->resources)),
            'pages' => (string) json_encode($compiled->pageMap(), JSON_UNESCAPED_SLASHES),
        ];
    }

    private function module(object $definition): Module
    {
        return $definition instanceof Module ? $definition : throw new InvalidArgumentException('Not a module.');
    }
}
