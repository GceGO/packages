<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Gcego\Components\Component;
use Gcego\Panels\Definitions\ModuleReference;
use Illuminate\Support\Facades\Gate;

/**
 * gcego:make-resource Subject --model=App\Models\Subject --module=academics
 * [--panel=admin] [--read-only] [--delete]: a Resource file in the module's
 * Resources/ folder, with a table, form and infolist derived from the model
 * (sensitive attributes left out) and the Create/Edit actions. Everything
 * is authorized by the model's policy: nothing is open by default.
 */
final class ResourceGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'resource';
    }

    public function description(): string
    {
        return 'A module resource (table, form, infolist, actions) for an Eloquent model';
    }

    public function options(): array
    {
        return ['model' => 'Eloquent model (required)', 'module' => 'Module (required)', 'panel' => 'Panel to check it is listed in', 'read-only' => 'No form, create or edit', 'delete' => 'Add the Delete action'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $module = $this->kit->names->identifier($context->required('module', 'the module to add the resource to (php artisan gcego:make-module first).'), 'module');
        $directory = $this->moduleDirectory($module);
        $model = $this->model($context, true, 'the Eloquent model the resource manages, e.g. --model="App\\Models\\Subject".');
        assert($model !== null);
        $plural = $names->plural($context->name);
        $name = $names->slug($plural);

        if ($plural === 'Resources' || in_array($name, ['index', 'create', 'view', 'edit', 'show'], true)) {
            throw GenerationException::invalid(sprintf('"%s" cannot be a resource name.', $context->name));
        }

        $inspection = $this->kit->models->inspect($model);
        array_push($this->warnings, ...$inspection['warnings']);
        $short = class_basename($model);
        $fields = $inspection['fields'];
        $readOnly = $context->flag('read-only') || array_filter($fields, static fn ($field): bool => $field->fillable) === [];

        if ($readOnly && ! $context->flag('read-only')) {
            $this->warn(sprintf('%s has no fillable attributes to edit: the resource is read-only.', $short));
        }

        $table = $this->kit->fields->table($fields);

        if ($table['code'] === 'new Table') {
            $table = ['code' => sprintf("new Table(new Text('%s', 'ID'))", $inspection['key']), 'imports' => ['Gcego\\Tables\\Table', 'Gcego\\Tables\\Columns\\Text']];
        }

        $infolist = $this->kit->fields->infolist($fields);
        $form = $readOnly ? ['code' => '', 'imports' => []] : $this->kit->fields->form($fields);
        $imports = ['Gcego\\Components\\Property', 'Gcego\\Data\\Source', 'Gcego\\Modules\\Resource', $model, ...$table['imports'], ...$form['imports']];
        $properties = sprintf("\n    new Property(Resource::RecordKey, '%s'),", $inspection['key']);

        if ($inspection['title'] !== null) {
            $properties .= sprintf("\n    new Property(Resource::RecordTitle, '%s'),", $inspection['title']);
        }

        $actions = '';

        if (! $readOnly) {
            $imports = [...$imports, 'Gcego\\Actions\\Create', 'Gcego\\Actions\\Edit'];
            $actions = "\n\n    new Create,\n    new Edit,";
        }

        if ($context->flag('delete')) {
            $imports[] = 'Gcego\\Actions\\Delete';
            $actions .= ($actions === '' ? "\n\n" : "\n").'    new Delete,';
        }

        $infolistCode = '';

        if ($infolist['code'] !== 'new Infolist') {
            $imports = [...$imports, ...$infolist['imports']];
            $infolistCode = "\n\n    ".$infolist['code'].',';
        }

        $this->guidance($model, $short, $module, $directory, $context->string('panel'), $readOnly);
        $code = $table['code'].$form['code'].$infolistCode;

        return [$this->file($context, $directory.'/Resources/'.$plural.'.php', 'resource', [
            'imports' => $this->imports($imports, $code.$properties),
            'name' => StubValue::string($name),
            'module' => StubValue::string($module),
            'model_class' => StubValue::identifier($short),
            'properties' => StubValue::code($properties),
            'table' => StubValue::code($table['code']),
            'form' => StubValue::code($form['code'] === '' || $form['code'] === 'new Form' ? '' : "\n\n    ".$form['code'].','),
            'infolist' => StubValue::code($infolistCode),
            'actions' => StubValue::code($actions),
        ], sprintf('Resource "%s" of module "%s"', $name, $module))];
    }

    protected function test(GenerationContext $context): array
    {
        return ['resource', (string) $context->string('module').'.'.$this->kit->names->slug($this->kit->names->plural($context->name))];
    }

    /**
     * @param  class-string  $model
     */
    private function guidance(string $model, string $short, string $module, string $directory, ?string $panel, bool $readOnly): void
    {
        if (Gate::getPolicyFor($model) === null) {
            $this->warn(sprintf('%s has no policy: its pages are denied until you add one (php artisan make:policy %sPolicy --model=%s).', $short, $short, $short));
        } else {
            $this->next(sprintf('Review %s\'s policy: viewAny and view%s decide who sees and changes records.', $short, $readOnly ? '' : ', create and update'));
        }

        $tenancy = $this->kit->config->get('gcego-security.tenancy');

        if (is_array($tenancy) && ($tenancy['enabled'] ?? false) === true && ! array_key_exists($model, is_array($tenancy['models'] ?? null) ? $tenancy['models'] : [])) {
            $this->warn(sprintf('Tenancy is enabled but %s is not in gcego-security.tenancy.models: its records are not tenant-scoped.', $short));
        }

        $moduleFile = (string) @file_get_contents($directory.'/Module.php');

        if (! str_contains($moduleFile, '/Resources/*.php')) {
            $this->next(sprintf("Module \"%s\" does not include Resources/ automatically: add require __DIR__.'/Resources/%s.php', to its new Module(...).", $module, $this->kit->names->plural($short)));
        }

        $panel = $this->assertPanel($panel);

        if ($panel !== null) {
            $definition = $this->kit->catalog->definition('panel:'.$panel);
            $references = $definition instanceof Component ? array_map(static fn ($child): ?string => $child instanceof ModuleReference ? $child->name() : null, iterator_to_array($definition->children())) : [];

            if (! in_array($module, $references, true)) {
                $this->next(sprintf("Add new ModuleReference('%s') to panel \"%s\" to show the resource there.", $module, $panel));
            }
        }
    }
}
