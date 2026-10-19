<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Composer\InstalledVersions;
use Gcego\Cli\Contracts\Generator;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\FileOperation;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\GenerationPlan;
use Gcego\Cli\Generation\PlannedFile;
use Gcego\Cli\Stubs\StubValue;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared planning steps: checking required packages, rendering stubs into
 * planned files (with their conflict status), locating modules, and adding
 * a test when asked.
 */
abstract class AbstractGenerator implements Generator
{
    /** @var list<string> */
    protected array $warnings = [];

    /** @var list<string> */
    protected array $nextSteps = [];

    public function __construct(protected readonly GenerationKit $kit) {}

    public function requires(): array
    {
        return [];
    }

    public function options(): array
    {
        return [];
    }

    final public function plan(GenerationContext $context): GenerationPlan
    {
        $this->warnings = [];
        $this->nextSteps = [];

        foreach ($this->requires() as $package) {
            if (! $this->installed($package)) {
                throw GenerationException::missing(sprintf('The %s generator needs %s: composer require %s', $this->id(), $package, $package));
            }
        }

        $files = $this->files($context);

        if (($context->flag('test') || $this->kit->cli->generateTests) && ($test = $this->test($context)) !== null) {
            $files = [...$files, ...$this->testFiles($context, ...$test)];
        }

        return new GenerationPlan($this->id(), $files, $this->requires(), array_values(array_unique($this->warnings)), [], array_values(array_unique($this->nextSteps)), $context->toArray());
    }

    /**
     * @return list<PlannedFile>
     */
    abstract protected function files(GenerationContext $context): array;

    /**
     * The test to generate with --test: [type, target], or null when there is none for this kind.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function test(GenerationContext $context): ?array
    {
        return null;
    }

    /**
     * @param  array<string, StubValue>  $values
     */
    protected function file(GenerationContext $context, string $path, string $stub, array $values, string $description, ?string $namespace = null, ?string $class = null): PlannedFile
    {
        $path = $this->kit->files->resolve($path);
        $contents = $this->kit->stubs->render($stub, $values);
        $existing = is_file($path) ? file_get_contents($path) : null;
        $operation = match (true) {
            $existing === null || $existing === false => FileOperation::Create,
            $existing === $contents => FileOperation::Unchanged,
            $context->force => FileOperation::Overwrite,
            default => FileOperation::Conflict,
        };

        return new PlannedFile($path, $this->kit->files->relative($path), $contents, $description, $operation, $namespace, $class);
    }

    /**
     * The folder of an existing module (from its Module.php), by name.
     */
    protected function moduleDirectory(string $module): string
    {
        $module = $this->kit->names->identifier($module, 'module');
        $descriptor = $this->kit->catalog->descriptor('module:'.$module);

        if ($descriptor !== null && $descriptor->source->type->value === 'file') {
            return dirname($descriptor->source->location);
        }

        // An invalid module (e.g. one whose resource broke) is not in the index: find it by convention.
        $conventional = $this->kit->paths->directory('module').'/'.$this->kit->names->className($module).'/Module.php';

        if ($descriptor === null && is_file($conventional)) {
            $this->warn(sprintf('The module "%s" is not valid right now (php artisan gcego:validate --type=modules).', $module));

            return dirname($conventional);
        }

        $known = $this->kit->catalog->names('module');

        throw GenerationException::missing(sprintf('The module "%s" does not exist%s. Create it with php artisan gcego:make-module %s.', $module, $known === [] ? '' : ' (modules: '.implode(', ', $known).')', $module));
    }

    /**
     * Refuses a name another file already defines; the file being regenerated itself is fine
     * (its content decides: unchanged, conflict or --force).
     */
    protected function assertAvailable(string $identity, string $path): void
    {
        $descriptor = $this->kit->catalog->descriptor($identity);

        if ($descriptor === null) {
            return;
        }

        $source = $descriptor->source->type->value === 'file' ? (realpath($descriptor->source->location) ?: $descriptor->source->location) : null;

        if ($source !== (realpath($path) ?: $path)) {
            throw GenerationException::invalid(sprintf('"%s" is already defined%s. Choose another name.', $identity, $source === null ? ' ('.$descriptor->source->toString().')' : ' in '.$this->kit->files->relative($source)));
        }
    }

    protected function assertPanel(?string $panel): ?string
    {
        if ($panel === null) {
            return null;
        }

        $panel = $this->kit->names->identifier($panel, 'panel');

        if (! $this->kit->catalog->has('panel:'.$panel)) {
            throw GenerationException::missing(sprintf('The panel "%s" does not exist. Create it with php artisan gcego:make-panel %s.', $panel, $panel));
        }

        return $panel;
    }

    /**
     * A requirement from --ability, or the given default.
     *
     * @return array{code: string, import: string}
     */
    protected function requirement(GenerationContext $context, string $default = 'authenticated', ?string $model = null): array
    {
        $ability = $context->string('ability');

        if ($ability !== null) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $ability) !== 1) {
                throw GenerationException::invalid(sprintf('--ability "%s" is not a valid ability name.', $ability));
            }

            return ['code' => sprintf("new Ability('%s'%s)", $ability, $model === null ? '' : ', '.class_basename($model).'::class'), 'import' => 'Gcego\\Security\\Requirements\\Ability'];
        }

        return $default === 'authenticated'
            ? ['code' => 'new Authenticated', 'import' => 'Gcego\\Security\\Requirements\\Authenticated']
            : ['code' => sprintf("new Ability('%s'%s)", $default, $model === null ? '' : ', '.class_basename($model).'::class'), 'import' => 'Gcego\\Security\\Requirements\\Ability'];
    }

    /**
     * @param  list<string>  $imports
     */
    protected function imports(array $imports, string $code = ''): StubValue
    {
        return StubValue::code($this->kit->fields->imports($imports, $code === '' ? implode("\n", $imports).' new Property(' : $code));
    }

    /**
     * @return class-string<Model>
     */
    protected function model(GenerationContext $context, bool $required, string $hint): ?string
    {
        $model = $context->string('model');

        if ($model === null) {
            if ($required) {
                throw GenerationException::invalid(sprintf('--model is required: %s', $hint));
            }

            return null;
        }

        return $this->kit->models->model($this->kit->names->qualifiedClass($model, 'model'));
    }

    protected function installed(string $package): bool
    {
        try {
            return InstalledVersions::isInstalled($package);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function warn(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    protected function next(string $step): void
    {
        $this->nextSteps[] = $step;
    }

    /**
     * @return list<PlannedFile>
     */
    private function testFiles(GenerationContext $context, string $type, string $target): array
    {
        if (! $this->installed('gcego/testing')) {
            $this->warn('No test was generated: --test needs gcego/testing (composer require --dev gcego/testing).');

            return [];
        }

        $generator = $this->kit->container->make(TestGenerator::class);
        $name = $this->kit->names->className($context->name).ucfirst($type).'Test';
        $plan = $generator->plan(new GenerationContext($name, ['type' => $type, 'target' => $target, 'pest' => $context->flag('pest'), 'phpunit' => $context->flag('phpunit')], $context->force));
        $this->warnings = [...$this->warnings, ...$plan->warnings];

        return $plan->files;
    }
}
