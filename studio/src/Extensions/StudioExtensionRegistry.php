<?php

declare(strict_types=1);

namespace Gcego\Studio\Extensions;

use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Exceptions\ExtensionException;
use Illuminate\Contracts\Container\Container;

/**
 * Trusted Studio extensions (configuration and Studio::extend()), applied
 * once in (priority, id) order. Ids must be unique and well formed (the
 * same extension class registered twice under its id counts once; two
 * classes claiming one id are refused); an
 * extension registered after Studio applied them is refused rather than
 * silently ignored.
 */
final class StudioExtensionRegistry
{
    /** @var array<string, StudioExtension> */
    private array $extensions = [];

    /** @var array<string, StudioExtensionContext>|null */
    private ?array $contexts = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<StudioExtension>|StudioExtension  $extension
     */
    public function add(string|StudioExtension $extension): void
    {
        if ($this->contexts !== null) {
            throw ExtensionException::invalid(is_string($extension) ? $extension : $extension->id(), 'was registered after Studio applied its extensions; register it while the application boots.');
        }

        if (is_string($extension)) {
            if (! class_exists($extension) || ! is_subclass_of($extension, StudioExtension::class)) {
                throw ExtensionException::invalid($extension, 'is not a class implementing '.StudioExtension::class.'.');
            }

            $instance = $this->container->make($extension);
            $extension = $instance instanceof StudioExtension ? $instance : throw ExtensionException::invalid($extension, 'could not be built.');
        }

        $id = $extension->id();

        if (preg_match('#^[a-z0-9][a-z0-9_.-]*(?:/[a-z0-9][a-z0-9_.-]*)?$#', $id) !== 1) {
            throw ExtensionException::invalid($id, 'needs an id such as "acme/reports".');
        }

        if (isset($this->extensions[$id])) {
            if ($extension::class === $this->extensions[$id]::class) {
                return;   // the same extension registered twice (configuration and Studio::extend()): once is enough
            }

            throw ExtensionException::invalid($id, 'is registered twice (by two different classes).');
        }

        $this->extensions[$id] = $extension;
    }

    /**
     * Runs every extension once and returns what they contributed, by extension id.
     *
     * @return array<string, StudioExtensionContext>
     */
    public function contexts(): array
    {
        if ($this->contexts !== null) {
            return $this->contexts;
        }

        $contexts = [];

        foreach ($this->ordered() as $id => $extension) {
            $context = new StudioExtensionContext($id);
            $extension->register($context);
            $contexts[$id] = $context;
        }

        return $this->contexts = $contexts;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->ordered());
    }

    /**
     * @return array<string, StudioExtension>
     */
    private function ordered(): array
    {
        $ordered = $this->extensions;
        uasort($ordered, static fn (StudioExtension $a, StudioExtension $b): int => [$a->priority(), $a->id()] <=> [$b->priority(), $b->id()]);

        return $ordered;
    }

    public function has(string $id): bool
    {
        return isset($this->extensions[$id]);
    }
}
