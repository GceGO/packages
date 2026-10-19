<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Gcego\Components\Component;
use Gcego\Security\Requirements\PublicAccess;

/**
 * gcego:make-test SubjectResourceTest --type=resource --target=academics.subjects
 * [--pest|--phpunit]: a test using gcego/testing's assertions. It checks what
 * holds for any application — the definition is registered and valid, its
 * integration is healthy, guests are refused — without assuming models,
 * users or data; add your own cases next to it.
 */
final class TestGenerator extends AbstractGenerator
{
    /** type => what --target names */
    public const array Types = [
        'component' => 'a registry identity ("widget:students") or a definition file ("app/GCEGo/Modules/Academics/Forms/SubjectForm.php")',
        'blueprint' => 'a blueprint name',
        'form' => 'a form definition file',
        'table' => 'a table definition file',
        'action' => 'definition/action ("page:academics-subjects/publish")',
        'resource' => 'module.resource ("academics.subjects")',
        'page' => 'a page name',
        'module' => 'a module name',
        'panel' => 'a panel name',
        'workflow' => 'a workflow name',
        'settings' => 'a settings group name',
        'analytics' => 'an analytics identity ("metric:active_students")',
        'security' => 'a Gate ability guests must not have',
        'tenant' => 'a tenant-scoped model class',
    ];

    public function id(): string
    {
        return 'test';
    }

    public function description(): string
    {
        return 'A Pest or PHPUnit test using gcego/testing';
    }

    public function requires(): array
    {
        return ['gcego/testing'];
    }

    public function options(): array
    {
        return ['type' => implode(', ', array_keys(self::Types)), 'target' => 'What the test checks (see --type)', 'pest' => 'Write a Pest test', 'phpunit' => 'Write a PHPUnit test'];
    }

    protected function files(GenerationContext $context): array
    {
        $class = $this->kit->names->className($context->name, 'test name');
        $class = str_ends_with($class, 'Test') ? $class : $class.'Test';
        $type = $context->required('type', 'one of '.implode(', ', array_keys(self::Types)).'.');

        if (! isset(self::Types[$type])) {
            throw GenerationException::invalid(sprintf('--type must be one of %s.', implode(', ', array_keys(self::Types))));
        }

        $target = $context->required('target', self::Types[$type].'.');
        $framework = match (true) {
            $context->flag('pest') && $context->flag('phpunit') => throw GenerationException::invalid('Choose --pest or --phpunit, not both.'),
            $context->flag('pest') => 'pest',
            $context->flag('phpunit') => 'phpunit',
            default => $this->installed('pestphp/pest') ? 'pest' : 'phpunit',
        };

        if ($framework === 'pest' && ! $this->installed('pestphp/pest')) {
            $this->warn('pestphp/pest is not installed: install it to run this test (composer require --dev pestphp/pest).');
        }

        ['imports' => $imports, 'lines' => $lines, 'description' => $description] = $this->body($type, $target, $framework);
        $directory = $this->kit->paths->directory('test');
        $path = $directory.'/'.$class.'.php';
        $indent = $framework === 'pest' ? '    ' : '        ';
        $values = [
            'imports' => $this->imports($imports, implode("\n", $lines)),
            'body' => StubValue::code(implode("\n", array_map(static fn (string $line): string => $line === '' ? '' : $indent.$line, $lines))),
        ];

        if ($framework === 'pest') {
            $values['description'] = StubValue::string($description);
        } else {
            $namespace = $this->kit->namespaces->forDirectory($directory);
            $values += ['namespace' => StubValue::identifier($namespace), 'class' => StubValue::identifier($class), 'method' => StubValue::code(str_replace(' ', '_', strtolower((string) preg_replace('/[^A-Za-z0-9 ]/', '', $description))))];
        }

        $this->next(sprintf('Run it: php artisan test %s', $this->kit->files->relative($path)));

        return [$this->file($context, $path, 'test.'.$framework, $values, ucfirst($framework).' test '.$class)];
    }

    /**
     * The test's statements for a type and target (also what this package's own tests execute).
     *
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    public function body(string $type, string $target, string $framework = 'pest'): array
    {
        $quoted = static fn (string $value): string => "'".StubValue::string($value)->rendered."'";
        $registry = 'Gcego\\Registry\\Contracts\\RegistryInterface';
        $test = 'Gcego\\Testing\\GcegoTest';

        return match ($type) {
            'component', 'form', 'table' => $this->componentBody($type, $target, $quoted),
            'blueprint' => ['imports' => [$test, 'Gcego\\Registry\\Registry'], 'lines' => [sprintf('GcegoTest::blueprint(app(Registry::class)->blueprint(%s))->assertValid()->assertName(%s);', $quoted($this->name($target, 'blueprint')), $quoted($this->name($target, 'blueprint')))], 'description' => sprintf('declares a valid blueprint %s', $target)],
            'action' => $this->actionBody($target, $quoted),
            'resource' => $this->resourceBody($target, $quoted),
            'page' => $this->pageBody($this->name($target, 'page'), $quoted),
            'module' => ['imports' => [$test], 'lines' => [sprintf('GcegoTest::module(%s)->assertRegistered()->assertHealthy();', $quoted($this->name($target, 'module')))], 'description' => sprintf('registers a healthy module %s', $target)],
            'panel' => ['imports' => [$test], 'lines' => [sprintf('GcegoTest::panel(%s)->assertRegistered()->assertHealthy();', $quoted($this->name($target, 'panel')))], 'description' => sprintf('registers a healthy panel %s', $target)],
            'workflow' => ['imports' => [$test, 'Gcego\\Workflows\\Registry\\WorkflowDefinitions'], 'lines' => [sprintf('GcegoTest::workflow(app(WorkflowDefinitions::class)->resolve(%s))->assertValid()->assertEveryStateReachable();', $quoted($this->name($target, 'workflow')))], 'description' => sprintf('declares a valid workflow %s', $target)],
            'settings' => ['imports' => [$test, $registry], 'lines' => [sprintf('GcegoTest::component(app(RegistryInterface::class)->get(%s))->assertValid();', $quoted('settings:'.$this->name($target, 'settings')))], 'description' => sprintf('declares valid settings %s', $target)],
            'analytics' => $this->analyticsBody($target, $quoted),
            'security' => $this->securityBody($target, $quoted),
            default => $this->tenantBody($target, $framework),
        };
    }

    private function name(string $target, string $subject): string
    {
        return $this->kit->names->identifier(str_contains($target, ':') ? substr($target, (int) strpos($target, ':') + 1) : $target, $subject);
    }

    /**
     * @param  \Closure(string): string  $quoted
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function componentBody(string $type, string $target, \Closure $quoted): array
    {
        $test = 'Gcego\\Testing\\GcegoTest';

        if (str_ends_with($target, '.php')) {
            $path = $this->kit->files->relative($this->kit->files->resolve($target));
            $load = sprintf('$definition = require base_path(%s);', $quoted($path));
            $assert = match ($type) {
                'form' => 'GcegoTest::form($definition)->assertHealthy();',
                'table' => 'GcegoTest::table($definition)->assertHealthy();',
                default => 'GcegoTest::component($definition)->assertValid();',
            };

            return ['imports' => [$test], 'lines' => [$load, '', $assert], 'description' => sprintf('declares a valid %s in %s', $type === 'component' ? 'definition' : $type, basename($path))];
        }

        if (preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9._-]*$/', $target) !== 1 || $type !== 'component') {
            throw GenerationException::invalid(sprintf('--target for a %s test must be %s.', $type, self::Types[$type]));
        }

        return ['imports' => [$test, 'Gcego\\Registry\\Contracts\\RegistryInterface'], 'lines' => [sprintf('GcegoTest::component(app(RegistryInterface::class)->get(%s))->assertValid();', $quoted($target))], 'description' => sprintf('declares a valid %s', $target)];
    }

    /**
     * @param  \Closure(string): string  $quoted
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function actionBody(string $target, \Closure $quoted): array
    {
        if (preg_match('#^([a-z][a-z0-9-]*:[a-z][a-z0-9._-]*)/([a-z][a-z0-9_-]*)$#', $target, $match) !== 1) {
            throw GenerationException::invalid('--target for an action test must be definition/action, e.g. page:academics-subjects/publish.');
        }

        return ['imports' => ['Gcego\\Testing\\GcegoTest'], 'lines' => [sprintf('GcegoTest::action(%s, %s)->actingAs(null)->execute()->assertDenied();', $quoted($match[1]), $quoted($match[2]))], 'description' => sprintf('refuses action %s to guests', $match[2])];
    }

    /**
     * @param  \Closure(string): string  $quoted
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function resourceBody(string $target, \Closure $quoted): array
    {
        if (preg_match('/^([a-z][a-z0-9-]*)\.([a-z][a-z0-9-]*)$/', $target, $match) !== 1) {
            throw GenerationException::invalid('--target for a resource test must be module.resource, e.g. academics.subjects.');
        }

        return ['imports' => ['Gcego\\Testing\\GcegoTest'], 'lines' => [sprintf('GcegoTest::module(%s)->assertHasResource(%s)->assertHealthy();', $quoted($match[1]), $quoted($match[2]))], 'description' => sprintf('declares resource %s in a healthy module', $target)];
    }

    /**
     * @param  \Closure(string): string  $quoted
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function pageBody(string $name, \Closure $quoted): array
    {
        $lines = [sprintf('GcegoTest::page(%s)->assertRegistered()->assertHealthy();', $quoted($name))];
        $page = $this->kit->catalog->definition('page:'.$name);
        $public = $page instanceof Component && array_filter(iterator_to_array($page->configurations()), static fn (object $node): bool => $node instanceof PublicAccess) !== [];

        if (! $public) {
            $lines[] = sprintf('GcegoTest::page(%s)->actingAs(null)->assertRequiresAuthentication();', $quoted($name));
        }

        return ['imports' => ['Gcego\\Testing\\GcegoTest'], 'lines' => $lines, 'description' => sprintf('registers page %s%s', $name, $public ? '' : ' for signed-in users')];
    }

    /**
     * @param  \Closure(string): string  $quoted
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function analyticsBody(string $target, \Closure $quoted): array
    {
        if (preg_match('/^(dataset|metric|report|analytics-dashboard):[a-z][a-z0-9_-]*$/', $target) !== 1) {
            throw GenerationException::invalid('--target for an analytics test must be dataset:, metric:, report: or analytics-dashboard:<name>.');
        }

        return ['imports' => ['Gcego\\Testing\\GcegoTest', 'Gcego\\Registry\\Contracts\\RegistryInterface'], 'lines' => [sprintf('GcegoTest::analytics(app(RegistryInterface::class)->get(%s))->assertValid();', $quoted($target))], 'description' => sprintf('declares a valid %s', $target)];
    }

    /**
     * @param  \Closure(string): string  $quoted
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function securityBody(string $target, \Closure $quoted): array
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $target) !== 1) {
            throw GenerationException::invalid('--target for a security test must be an ability name.');
        }

        return ['imports' => ['Gcego\\Testing\\GcegoTest'], 'lines' => [sprintf('GcegoTest::security()->actingAs(null)->assertCannot(%s);', $quoted($target))], 'description' => sprintf('does not grant %s to guests', $target)];
    }

    /**
     * @return array{imports: list<string>, lines: list<string>, description: string}
     */
    private function tenantBody(string $target, string $framework): array
    {
        $model = $this->kit->models->model($this->kit->names->qualifiedClass($target, 'model'));
        $short = class_basename($model);
        $lines = $framework === 'pest'
            ? [sprintf("expect(config('gcego-security.tenancy.enabled'))->toBeTrue()->and(config('gcego-security.tenancy.models'))->toHaveKey(%s::class);", $short)]
            : ["\$this->assertTrue(config('gcego-security.tenancy.enabled'));", sprintf("\$this->assertArrayHasKey(%s::class, config('gcego-security.tenancy.models'));", $short)];
        $lines[] = sprintf('GcegoTest::security()->assertPolicyApplied(%s::class);', $short);

        return ['imports' => ['Gcego\\Testing\\GcegoTest', $model], 'lines' => $lines, 'description' => sprintf('keeps %s tenant scoped and policy guarded', $short)];
    }
}
