<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Modules\Contracts\ModuleExtension;
use Gcego\Modules\Contracts\ModuleLifecycleHook;
use Gcego\Modules\Exceptions\InvalidModuleException;

/**
 * Validated gcego-modules configuration (a container singleton, plain data).
 */
final readonly class ModulesConfig
{
    /**
     * @param  list<string>  $paths
     * @param  list<Operation>  $defaultOperations
     * @param  list<class-string<ModuleExtension>>  $extensions
     * @param  list<class-string<ModuleLifecycleHook>>  $hooks
     */
    public function __construct(
        public bool $discovery = true,
        public array $paths = [],
        public string $entrypoint = '#(^|/)Module\.php$#',
        public bool $autoPages = true,
        public array $defaultOperations = [],
        public bool $autoNavigation = false,
        public string $navigationTarget = 'admin',
        public bool $relationships = true,
        public bool $authorizeByDefault = true,
        public array $extensions = [],
        public array $hooks = [],
    ) {
        if (@preg_match($entrypoint, '') === false) {
            throw InvalidModuleException::invalid('gcego-modules.discovery.entrypoint', 'use a regular expression such as "#(^|/)Module\.php$#".');
        }

        if (preg_match('/^(?:navigation:)?[a-z][a-z0-9_-]{0,63}$/', $navigationTarget) !== 1) {
            throw InvalidModuleException::invalid('gcego-modules.navigation.target', 'use a navigation name such as "admin".');
        }

        foreach ($extensions as $extension) {
            if (! is_a($extension, ModuleExtension::class, true)) {
                throw InvalidModuleException::invalid('gcego-modules.extensions', sprintf('%s does not implement %s.', $extension, ModuleExtension::class));
            }
        }

        foreach ($hooks as $hook) {
            if (! is_a($hook, ModuleLifecycleHook::class, true)) {
                throw InvalidModuleException::invalid('gcego-modules.lifecycle.hooks', sprintf('%s does not implement %s.', $hook, ModuleLifecycleHook::class));
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $read = static function (string $section, string $key, mixed $default) use ($config): mixed {
            $values = $config[$section] ?? [];

            return is_array($values) && array_key_exists($key, $values) ? $values[$key] : $default;
        };
        $bool = static fn (string $section, string $key, bool $default): bool => is_bool($value = $read($section, $key, $default)) ? $value : throw InvalidModuleException::invalid("gcego-modules.$section.$key", 'must be true or false.');
        $string = static fn (string $section, string $key, string $default): string => is_string($value = $read($section, $key, $default)) ? $value : throw InvalidModuleException::invalid("gcego-modules.$section.$key", 'must be a string.');
        $strings = static function (mixed $value, string $key): array {
            if (! is_array($value) || array_filter($value, static fn (mixed $entry): bool => ! is_string($entry)) !== []) {
                throw InvalidModuleException::invalid('gcego-modules.'.$key, 'must be a list of strings.');
            }

            /** @var list<string> */
            return array_values($value);
        };
        $operations = [];

        foreach ($strings($read('resources', 'default_operations', []), 'resources.default_operations') as $operation) {
            $operations[] = Operation::tryFrom($operation) ?? throw InvalidModuleException::invalid('gcego-modules.resources.default_operations', sprintf('"%s" is not an operation (use %s).', $operation, implode(', ', Operation::values())));
        }

        /** @var list<class-string<ModuleExtension>> $extensions */
        $extensions = $strings($config['extensions'] ?? [], 'extensions');
        /** @var list<class-string<ModuleLifecycleHook>> $hooks */
        $hooks = $strings($read('lifecycle', 'hooks', []), 'lifecycle.hooks');

        return new self(
            $bool('discovery', 'enabled', true),
            $strings($read('discovery', 'paths', []), 'discovery.paths'),
            $string('discovery', 'entrypoint', '#(^|/)Module\.php$#'),
            $bool('resources', 'auto_pages', true),
            $operations,
            $bool('navigation', 'auto', false),
            $string('navigation', 'target', 'admin'),
            $bool('relationships', 'enabled', true),
            $bool('security', 'authorize_by_default', true),
            $extensions,
            $hooks,
        );
    }
}
