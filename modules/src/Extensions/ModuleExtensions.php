<?php

declare(strict_types=1);

namespace Gcego\Modules\Extensions;

use Gcego\Modules\Contracts\ModuleExtension;
use Gcego\Modules\Exceptions\InvalidModuleException;
use Gcego\Modules\Module;
use Gcego\Modules\ModulesConfig;
use Illuminate\Contracts\Container\Container;

/**
 * The trusted extensions: gcego-modules.extensions, then those registered
 * in code (ModuleManager::extend()), applied in that order. Registering the
 * same class twice is a no-op; extensions never come from requests.
 */
final class ModuleExtensions
{
    /**
     * @var list<class-string<ModuleExtension>>
     */
    private array $registered;

    public function __construct(ModulesConfig $config, private readonly Container $container)
    {
        $this->registered = $config->extensions;
    }

    /**
     * @param  class-string<ModuleExtension>  $extension
     */
    public function register(string $extension): void
    {
        if (! is_a($extension, ModuleExtension::class, true)) {
            throw InvalidModuleException::invalid('extension '.$extension, 'it does not implement '.ModuleExtension::class.'.');
        }

        if (! in_array($extension, $this->registered, true)) {
            $this->registered[] = $extension;
        }
    }

    /**
     * @return list<class-string<ModuleExtension>>
     */
    public function all(): array
    {
        return $this->registered;
    }

    /**
     * @return list<class-string<ModuleExtension>>
     */
    public function for(string $module): array
    {
        return array_values(array_filter($this->registered, fn (string $class): bool => $this->make($class)->extends($module)));
    }

    /**
     * The module with every applicable extension applied; each result must
     * still be a Module with the same name.
     *
     * @throws InvalidModuleException
     */
    public function apply(Module $module): Module
    {
        $name = $module->moduleName();

        foreach ($this->for($name) as $class) {
            $extended = $this->make($class)->extend($module);

            if ($extended->moduleName() !== $name) {
                throw InvalidModuleException::invalid('extension '.$class, sprintf('it renamed the module "%s"; extensions may add to a module, not replace it.', $name));
            }

            $module = $extended;
        }

        return $module;
    }

    /**
     * @param  class-string<ModuleExtension>  $class
     */
    private function make(string $class): ModuleExtension
    {
        $extension = $this->container->make($class);

        return $extension instanceof ModuleExtension ? $extension : throw InvalidModuleException::invalid('extension '.$class, 'it does not implement '.ModuleExtension::class.'.');
    }
}
