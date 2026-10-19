<?php

declare(strict_types=1);

namespace Gcego\Testing\Fixtures;

use FilesystemIterator;
use Gcego\Testing\Exceptions\InvalidFixtureException;
use Gcego\Testing\Exceptions\TestEnvironmentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A temporary directory owned by one test: unique per process and
 * parallel-testing token, never inside the application, and removed by
 * remove() (the FixtureManager removes every workspace on cleanup).
 */
final class Workspace
{
    private bool $removed = false;

    private function __construct(public readonly string $root) {}

    public static function create(?string $base = null): self
    {
        $base = rtrim($base ?? sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $token = getenv('TEST_TOKEN');
        $root = $base.DIRECTORY_SEPARATOR.sprintf('gcego-testing-%d-%s-%s', getmypid(), is_string($token) && $token !== '' ? $token : '0', bin2hex(random_bytes(6)));

        if (! @mkdir($root, 0o777, true) && ! is_dir($root)) {
            throw TestEnvironmentException::because(sprintf('the workspace "%s" could not be created.', $root));
        }

        return new self((string) realpath($root));
    }

    public function path(string $relative = ''): string
    {
        $this->guard($relative);

        return $relative === '' ? $this->root : $this->root.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }

    public function directory(string $relative): string
    {
        $path = $this->path($relative);

        if (! is_dir($path)) {
            mkdir($path, 0o777, true);
        }

        return $path;
    }

    public function file(string $relative, string $contents): string
    {
        $path = $this->path($relative);
        $this->directory(dirname($relative) === '.' ? '' : dirname($relative));
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * A PHP file that returns the expression, e.g. "new Blueprint('x')".
     *
     * @param  list<class-string>  $imports
     */
    public function php(string $relative, string $expression, array $imports = []): string
    {
        $uses = implode('', array_map(static fn (string $class): string => 'use '.$class.";\n", $imports));

        return $this->file($relative, "<?php\n\ndeclare(strict_types=1);\n\n".$uses."\nreturn ".rtrim($expression, "; \n").";\n");
    }

    public function exists(): bool
    {
        return ! $this->removed && is_dir($this->root);
    }

    public function remove(): void
    {
        if ($this->removed || ! is_dir($this->root)) {
            $this->removed = true;

            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
        $this->removed = true;
    }

    private function guard(string $relative): void
    {
        if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw InvalidFixtureException::invalid($relative, 'must be a path inside the workspace');
        }
    }
}
