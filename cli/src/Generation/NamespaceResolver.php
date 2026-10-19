<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\Exceptions\GenerationException;

/**
 * Maps a target directory to its PHP namespace with the application's
 * Composer PSR-4 autoload rules (composer.json "autoload" and
 * "autoload-dev"), so generated classes are autoloadable where they land.
 */
final class NamespaceResolver
{
    /** @var array<string, string>|null directory (absolute, no trailing slash) => namespace prefix (no trailing \) */
    private ?array $map = null;

    public function __construct(private readonly PathResolver $paths, private readonly NameNormalizer $names) {}

    /**
     * The namespace of classes in $directory.
     *
     * @throws GenerationException when no PSR-4 rule covers the directory
     */
    public function forDirectory(string $directory): string
    {
        $directory = rtrim($directory, '/');
        $best = null;

        foreach ($this->map() as $root => $prefix) {
            if (($directory === $root || str_starts_with($directory, $root.'/')) && ($best === null || strlen($root) > strlen($best))) {
                $best = $root;
            }
        }

        if ($best === null) {
            throw GenerationException::invalid(sprintf('%s is not covered by a PSR-4 rule in composer.json, so a class there could not be autoloaded.', $this->paths->relative($directory)));
        }

        $namespace = $this->map()[$best];
        $rest = trim(substr($directory, strlen($best)), '/');

        foreach ($rest === '' ? [] : explode('/', $rest) as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                throw GenerationException::invalid(sprintf('The folder "%s" is not a valid namespace segment.', $segment));
            }

            $namespace .= '\\'.$segment;
        }

        return ltrim($namespace, '\\');
    }

    /**
     * Fully qualified name of $class in $directory, refusing to shadow an existing class elsewhere.
     */
    public function qualify(string $directory, string $class): string
    {
        $qualified = $this->forDirectory($directory).'\\'.$this->names->className($class, 'class name');

        if (class_exists($qualified, false) && ! str_starts_with((string) (new \ReflectionClass($qualified))->getFileName(), rtrim($directory, '/').'/')) {
            throw GenerationException::invalid(sprintf('%s already exists in another file.', $qualified));
        }

        return $qualified;
    }

    /**
     * @return array<string, string>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];
        $file = $this->paths->basePath().'/composer.json';
        $composer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        foreach (['autoload', 'autoload-dev'] as $section) {
            $autoload = is_array($composer) && is_array($composer[$section] ?? null) ? $composer[$section] : [];
            $rules = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];

            foreach ($rules as $prefix => $directories) {
                foreach ((array) $directories as $directory) {
                    if (is_string($prefix) && is_string($directory) && ! str_contains($directory, '..')) {
                        $map[rtrim($this->paths->basePath().'/'.trim($directory, '/'), '/')] = rtrim($prefix, '\\');
                    }
                }
            }
        }

        return $this->map = $map;
    }
}
