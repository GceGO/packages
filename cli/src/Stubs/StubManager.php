<?php

declare(strict_types=1);

namespace Gcego\Cli\Stubs;

use Gcego\Cli\Exceptions\ExtensionException;
use Gcego\Cli\Exceptions\StubException;

/**
 * Finds stubs in a fixed order of precedence:
 *
 *  1. the application's overrides (gcego-cli.stubs.custom_path, default stubs/gcego/<name>.stub);
 *  2. stub directories contributed by extension packages (a name may be
 *     contributed once: two packages providing the same stub is an error);
 *  3. gcego/cli's built-in stubs.
 *
 * Stub names are validated, so a name can never point outside these directories.
 */
final class StubManager
{
    /** @var array<string, string> stub name => file, from extension directories */
    private array $contributed = [];

    /** @var array<string, string> stub name => contributing package */
    private array $owners = [];

    public function __construct(private readonly string $builtIn, private readonly ?string $custom) {}

    /**
     * Adds an extension package's stubs ($directory/<name>.stub). A name the
     * built-in stubs already have is allowed (it overrides them); a name
     * another package contributed is refused.
     */
    public function addDirectory(string $package, string $directory): void
    {
        if (! is_dir($directory)) {
            throw ExtensionException::because(sprintf('The stub directory %s of %s does not exist.', $directory, $package));
        }

        foreach (glob(rtrim($directory, '/').'/*.stub') ?: [] as $file) {
            $name = basename($file, '.stub');

            if (isset($this->owners[$name]) && $this->owners[$name] !== $package) {
                throw ExtensionException::because(sprintf('The stub "%s" is contributed by both %s and %s.', $name, $this->owners[$name], $package));
            }

            $this->owners[$name] = $package;
            $this->contributed[$name] = $file;
        }
    }

    public function path(string $name): string
    {
        $this->validate($name);

        if ($this->custom !== null && is_file($file = rtrim($this->custom, '/').'/'.$name.'.stub') && ! is_link($file)) {
            return $file;
        }

        if (isset($this->contributed[$name])) {
            return $this->contributed[$name];
        }

        if (is_file($file = rtrim($this->builtIn, '/').'/'.$name.'.stub')) {
            return $file;
        }

        throw StubException::because($name, 'does not exist.');
    }

    public function contents(string $name): string
    {
        $contents = file_get_contents($this->path($name));

        if ($contents === false) {
            throw StubException::because($name, 'could not be read.');
        }

        return $contents;
    }

    /**
     * Where each stub comes from: "custom", the contributing package, or "built-in".
     *
     * @return array<string, array{source: string, path: string}>
     */
    public function all(): array
    {
        $names = array_map(static fn (string $file): string => basename($file, '.stub'), glob(rtrim($this->builtIn, '/').'/*.stub') ?: []);
        $names = array_values(array_unique([...$names, ...array_keys($this->contributed)]));
        sort($names);
        $all = [];

        foreach ($names as $name) {
            $path = $this->path($name);
            $all[$name] = ['source' => match (true) {
                $this->custom !== null && str_starts_with($path, rtrim($this->custom, '/').'/') => 'custom',
                isset($this->contributed[$name]) && $path === $this->contributed[$name] => $this->owners[$name],
                default => 'built-in',
            }, 'path' => $path];
        }

        return $all;
    }

    private function validate(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)?$/', $name) !== 1) {
            throw StubException::because($name, 'is not a valid stub name.');
        }
    }
}
