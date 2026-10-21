<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * A throwaway application directory with its own autoloaded namespace.
 *
 * Module entrypoints declare classes, and a PHP class can be declared once
 * per process, so every workspace uses a fresh namespace ("Ws1a2b3c\...").
 */
final class Workspace
{
    public readonly string $root;

    public readonly string $namespace;

    /** @var \Closure(string): void */
    private readonly \Closure $autoloader;

    public function __construct()
    {
        $id = bin2hex(random_bytes(6));
        $root = sys_get_temp_dir().'/gcego-blueprints-'.$id;

        if (! mkdir($root, 0777, true) && ! is_dir($root)) {
            throw new RuntimeException('Cannot create '.$root);
        }

        $this->root = (string) realpath($root);
        $this->namespace = 'Ws'.$id;
        $this->autoloader = function (string $class): void {
            if (str_starts_with($class, $this->namespace.'\\')) {
                $path = $this->root.'/'.str_replace('\\', '/', substr($class, strlen($this->namespace) + 1)).'.php';

                if (is_file($path)) {
                    require $path;
                }
            }
        };
        spl_autoload_register($this->autoloader);
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->root : $this->root.'/'.$relative;
    }

    public function file(string $relative, string $contents): string
    {
        $path = $this->path($relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
        clearstatcache(true, $path);

        return $path;
    }

    /**
     * The class name of a file under the workspace ("Modules/Academics/Subjects/Pages/ListSubjects").
     */
    public function className(string $relative): string
    {
        return $this->namespace.'\\'.str_replace('/', '\\', $relative);
    }

    /**
     * A plain supporting class at the given path (without ".php").
     */
    public function supportingClass(string $relative): string
    {
        $class = $this->className($relative);
        $separator = (int) strrpos($class, '\\');
        $this->file($relative.'.php', sprintf("<?php\n\nnamespace %s;\n\nfinal class %s {}\n", substr($class, 0, $separator), substr($class, $separator + 1)));

        return $class;
    }

    /**
     * A class-based blueprint entrypoint: $directory/<basename>.php declaring
     * a BlueprintProvider whose blueprint() returns Blueprint::define($definitions).
     *
     * $definitions is PHP source; "{ns}" is replaced by the module's namespace.
     * Without $returnsClass the file ends after the class (a class entrypoint).
     */
    public function blueprintModule(string $directory, string $definitions, bool $returnsClass = true): string
    {
        $class = basename($directory);
        $namespace = $this->className($directory);
        $definitions = str_replace('{ns}', $namespace, $definitions);
        $return = $returnsClass ? "return {$class}::class;" : '';

        return $this->file($directory.'/'.$class.'.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Gcego\\Blueprints\\Blueprint;
            use Gcego\\Blueprints\\Definitions\\{Actions, Derive, Description, Form, Infolist, Model, Name, Pages, Relations, Table, Title, Widgets};
            use Gcego\\Blueprints\\Registry\\{BlueprintProvider, ProvidesBlueprint};

            final class {$class} implements BlueprintProvider
            {
                use ProvidesBlueprint;

                public function blueprint(): Blueprint
                {
                    return Blueprint::define(
                        {$definitions}
                    );
                }
            }

            {$return}
            PHP);
    }

    public function remove(): void
    {
        spl_autoload_unregister($this->autoloader);

        if (! is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $item) {
            if ($item instanceof SplFileInfo) {
                $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }

        rmdir($this->root);
    }
}
