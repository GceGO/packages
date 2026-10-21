<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-blueprint Subjects [--model=] [--module=]: a gcego/blueprints
 * Blueprint where gcego/registry discovers blueprints (by default
 * app/GCEGo/Modules/Subjects/Subjects.php). With --model it gets a Source,
 * a table and (for fillable attributes) a form, guarded by the model's
 * "viewAny" policy ability.
 */
final class BlueprintGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'blueprint';
    }

    public function description(): string
    {
        return 'A blueprint (gcego/blueprints), discovered by gcego/registry';
    }

    public function options(): array
    {
        return ['model' => 'Eloquent model: adds a Source, table and form', 'module' => 'Place it inside this module\'s folder', 'title' => 'Title'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $class = $names->className($context->name);
        $name = $names->slug($context->name);
        $title = $context->string('title') ?? $names->title($context->name);
        $root = $context->string('module') !== null ? $this->moduleDirectory((string) $context->string('module')) : $this->kit->paths->directory('blueprint');
        $entrypoint = $this->kit->paths->entrypoint('blueprint');
        $path = $entrypoint === 'directory' ? $root.'/'.$class.'/'.$class.'.php' : $root.'/'.$class.'.php';

        $this->assertAvailable('blueprint:'.$name, $path);

        if (! $this->kit->paths->isDiscovered('blueprint', dirname($path))) {
            $this->warn(sprintf('%s is not a blueprint discovery folder (gcego-registry.discovery.roots): register the blueprint yourself.', $this->kit->files->relative(dirname($path))));
        }

        $imports = ['Gcego\\Blueprints\\Blueprint', 'Gcego\\Components\\Properties\\Property'];
        $parts = '';
        $model = $this->model($context, false, '');

        if ($model !== null) {
            $inspection = $this->kit->models->inspect($model);
            array_push($this->warnings, ...$inspection['warnings']);
            $table = $this->kit->fields->table($inspection['fields']);
            $form = $this->kit->fields->form($inspection['fields']);
            $imports = [...$imports, 'Gcego\\Data\\Source', 'Gcego\\Security\\Requirements\\Ability', $model, ...$table['imports'], ...$form['imports']];
            $short = class_basename($model);
            $parts = sprintf("\n    new Ability('viewAny', %s::class),\n    new Source(%s::class),\n\n    %s,", $short, $short, $table['code']);

            if ($form['code'] !== 'new Form') {
                $parts .= "\n\n    ".$form['code'].',';
            }

            $this->next(sprintf('Give %s a policy (viewAny, view, create, update): the blueprint denies without one.', $short));
        }

        return [$this->file($context, $path, 'blueprint', [
            'imports' => $this->imports($imports, $parts),
            'name' => StubValue::string($name),
            'title' => StubValue::string($title),
            'parts' => StubValue::code($parts),
        ], 'Blueprint "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['blueprint', $this->kit->names->slug($context->name)];
    }
}
