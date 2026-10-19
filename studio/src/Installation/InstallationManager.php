<?php

declare(strict_types=1);

namespace Gcego\Studio\Installation;

use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Contracts\InstallationStep;
use Gcego\Studio\Exceptions\InstallationException;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Integration\PackageIntegrationManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Plans and performs a safe installation:
 *
 * - config/gcego-studio.php (published from the package);
 * - a starter panel (<panels discovery path>/<panel>.php) whose access is
 *   an undefined Gate ability until you define it — nobody gets in by
 *   default — and its dashboard page (Authenticated);
 * - optionally a read-only example module for an existing model;
 * - files from extensions' InstallationSteps.
 *
 * Existing files are never touched unless their content differs and
 * --force is given; identical files count as installed (idempotent). No
 * users, roles, permissions or migrations are created; nothing outside
 * the application directory is written.
 */
final readonly class InstallationManager
{
    public function __construct(
        private Application $app,
        private Repository $config,
        private Container $container,
        private StudioConfiguration $studio,
        private PackageIntegrationManager $packages,
        private StudioExtensionRegistry $extensions,
    ) {}

    /**
     * @param  class-string<Model>|''|null  $model  '' for a value that is not a model class (reported).
     */
    public function plan(?string $panel = null, ?string $module = null, ?string $model = null, bool $force = false): InstallationPlan
    {
        $panel ??= $this->studio->installPanel;
        $problems = $this->packages->problems();

        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $panel) !== 1 || strlen($panel) > 40) {
            $problems[] = 'The panel name must be lowercase letters, digits and dashes (for example "admin").';

            return new InstallationPlan([], array_values(array_unique($problems)), []);
        }

        $files = [];
        $files[] = new PlannedFile($this->app->configPath('gcego-studio.php'), $this->read(dirname(__DIR__, 2).'/config/gcego-studio.php'), 'Studio configuration');

        $moduleName = null;

        if ($module !== null || $model !== null) {
            [$moduleFile, $moduleName, $moduleProblems] = $this->module($module, $model);
            $problems = [...$problems, ...$moduleProblems];

            if ($moduleFile !== null) {
                $files[] = $moduleFile;
            }
        }

        $title = Str::headline($panel);

        try {
            $files = [...$files, ...$this->starterFiles($panel, $title, $moduleName)];
        } catch (InstallationException $exception) {
            return new InstallationPlan([], [...$problems, $exception->getMessage()], []);
        }

        foreach ($this->extensions->contexts() as $context) {
            foreach ($context->steps() as $step) {
                $instance = $this->container->make($step);

                if ($instance instanceof InstallationStep) {
                    $files = [...$files, ...$instance->files()];
                }
            }
        }

        $planned = [];

        foreach ($files as $file) {
            if (! $this->inside($file->path)) {
                $problems[] = sprintf('Refusing to write outside the application: %s.', $file->path);

                continue;
            }

            $planned[] = $file->withStatus(match (true) {
                ! is_file($file->path) => PlannedFile::Create,
                $this->read($file->path) === $file->contents => PlannedFile::Unchanged,
                $force => PlannedFile::Overwrite,
                default => PlannedFile::Conflict,
            });
        }

        return new InstallationPlan($planned, array_values(array_unique($problems)), $this->nextSteps($panel, $moduleName));
    }

    /**
     * @param  list<string>  $confirmed  Conflicting paths the user agreed to overwrite (interactive).
     */
    public function install(InstallationPlan $plan, array $confirmed = []): InstallationResult
    {
        if ($plan->problems !== []) {
            return new InstallationResult(InstallationResult::Failed, errors: $plan->problems);
        }

        $created = [];
        $overwritten = [];
        $unchanged = [];
        $kept = [];
        $errors = [];

        foreach ($plan->files as $file) {
            $relative = $this->relative($file->path);
            $write = $file->writes() || ($file->status === PlannedFile::Conflict && in_array($file->path, $confirmed, true));

            if ($file->status === PlannedFile::Unchanged) {
                $unchanged[] = $relative;

                continue;
            }

            if (! $write) {
                $kept[] = $relative;

                continue;
            }

            try {
                $this->write($file->path, $file->contents);
                $file->status === PlannedFile::Create ? $created[] = $relative : $overwritten[] = $relative;
            } catch (Throwable $exception) {
                $errors[] = $relative.': '.$exception->getMessage();
            }
        }

        $status = $errors !== [] || $kept !== [] ? InstallationResult::Partial : InstallationResult::Installed;

        return new InstallationResult($status, $created, $overwritten, $unchanged, $kept, $errors, $plan->nextSteps);
    }

    public function relative(string $path): string
    {
        return ltrim(str_replace(rtrim($this->app->basePath(), '/'), '', $path), '/');
    }

    /**
     * @return array{0: PlannedFile|null, 1: string|null, 2: list<string>}
     */
    private function module(?string $module, ?string $model): array
    {
        $problems = [];

        if ($model === null || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return [null, null, [sprintf('--model must name an existing Eloquent model class (given: %s).', $model === null ? 'nothing' : mb_substr($model, 0, 120))]];
        }

        $folder = $module ?? Str::pluralStudly(class_basename($model));

        if (preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', $folder) !== 1) {
            return [null, null, ['--module must be a StudlyCase name such as "Academics".']];
        }

        $name = Str::kebab($folder);
        $key = (new $model)->getKeyName();

        $file = new PlannedFile($this->directory('modules').'/'.$folder.'/Module.php', $this->render('module', [
            'name' => $name,
            'title' => Str::headline($folder),
            'model' => ltrim($model, '\\'),
            'model_class' => class_basename($model),
            'key' => is_string($key) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1 ? $key : 'id',
        ]), 'Example module "'.$name.'" (read-only)');

        return [$file, $name, $problems];
    }

    /**
     * The first discovery folder of a kind, as the packages configure it.
     */
    private function directory(string $kind): string
    {
        $paths = match ($kind) {
            'panels' => $this->config->get('gcego-panels.discovery.paths'),
            'modules' => $this->config->get('gcego-modules.discovery.paths'),
            default => array_values(array_map(static fn (array $root): mixed => $root['path'] ?? null, array_filter((array) $this->config->get('gcego-registry.discovery.roots', []), static fn (mixed $root): bool => is_array($root) && ($root['kind'] ?? null) === 'page'))),
        };

        $first = is_array($paths) ? ($paths[0] ?? null) : null;

        return is_string($first) && $first !== '' ? rtrim($first, '/') : $this->app->path('GCEGo/'.ucfirst($kind));
    }

    /**
     * The starter panel and its dashboard.
     *
     * @return list<PlannedFile>
     */
    private function starterFiles(string $panel, string $title, ?string $moduleName): array
    {
        return [
            new PlannedFile($this->directory('panels').'/'.$panel.'.php', $this->render('panel', [
                'name' => $panel,
                'title' => $title,
                'path' => $panel,
                'ability' => $this->studio->installAbility,
                'module_import' => $moduleName === null ? '' : "\nuse Gcego\\Panels\\Definitions\\ModuleReference;",
                'module_reference' => $moduleName === null ? '' : "\n    new ModuleReference('".$moduleName."'),",
            ]), 'Starter panel "'.$panel.'"'),
            new PlannedFile($this->directory('pages').'/dashboard.php', $this->render('page', ['title' => $title]), 'Dashboard page'),
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $stub, array $values): string
    {
        $custom = $this->app->basePath('stubs/gcego-studio/'.$stub.'.stub');
        $template = is_file($custom) ? $this->read($custom) : $this->read(dirname(__DIR__, 2).'/stubs/'.$stub.'.stub');

        foreach ($values as $key => $value) {
            if (! preg_match('/^[\pL\pN _\-.:;\\\\\'\n(),]*$/u', $value)) {
                throw InstallationException::because(sprintf('The value of "%s" contains characters that cannot be written into PHP.', $key));
            }

            $template = str_replace('{{ '.$key.' }}', $key === 'title' ? str_replace("'", "\\'", $value) : $value, $template);
        }

        if (preg_match('/\{\{ [a-z_]+ \}\}/', $template, $match) === 1) {
            throw InstallationException::because(sprintf('The stub "%s" has an unknown placeholder %s.', $stub, $match[0]));
        }

        return $template;
    }

    private function inside(string $path): bool
    {
        $base = rtrim($this->app->basePath(), '/').'/';
        $normalized = $this->normalize($path);

        return str_starts_with($normalized.'/', $base) || str_starts_with($normalized, $base);
    }

    private function normalize(string $path): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && $segment !== '') {
                $parts[] = $segment;
            }
        }

        return '/'.implode('/', $parts);
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw InstallationException::because('the directory could not be created.');
        }

        if (is_link($path)) {
            throw InstallationException::because('the target is a symbolic link; refusing to write through it.');
        }

        $temporary = $directory.'/.'.basename($path).'.'.Str::random(8).'.tmp';

        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw InstallationException::because('the file could not be written.');
        }
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    /**
     * @return list<string>
     */
    private function nextSteps(string $panel, ?string $module): array
    {
        return array_values(array_filter([
            sprintf('Define who may enter the panel, for example in AppServiceProvider::boot(): Gate::define(\'%s\', fn ($user) => $user->is_admin); nobody can enter until you do.', $this->studio->installAbility),
            'Make sure Laravel authentication is set up (gcego/sentinel, or your own "login" route).',
            'Add gcego/chroma to resources/css/app.css: @import "../../vendor/gcego/chroma/resources/css/gcego.css"; then run npm run build.',
            $module === null ? null : sprintf('Define a policy for the model of module "%s" (viewAny, view); its pages are denied without one.', $module),
            sprintf('Run php artisan serve and open /%s; check everything with php artisan gcego:studio:health.', $panel),
        ]));
    }
}
