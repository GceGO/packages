<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\ModelField;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-form / gcego:make-table / gcego:make-infolist SubjectForm
 * --module=academics [--model=]: a reusable gcego/forms Form, gcego/tables
 * Table or gcego/infolists Infolist in the module's Forms/, Tables/ or
 * Infolists/ folder, for a Resource or Blueprint to require. Fields come
 * from the model when given (sensitive attributes left out).
 */
final class PartGenerator extends AbstractGenerator
{
    private const array Parts = [
        'form' => ['Forms', 'A reusable form (gcego/forms) for a resource or blueprint'],
        'table' => ['Tables', 'A reusable table (gcego/tables) for a resource or blueprint'],
        'infolist' => ['Infolists', 'Reusable record details (gcego/infolists) for a resource or blueprint'],
    ];

    private string $part = 'form';

    public function forPart(string $part): self
    {
        if (! isset(self::Parts[$part])) {
            throw GenerationException::invalid(sprintf('Unknown part "%s".', $part));
        }

        $generator = clone $this;
        $generator->part = $part;

        return $generator;
    }

    public function id(): string
    {
        return $this->part;
    }

    public function description(): string
    {
        return self::Parts[$this->part][1];
    }

    public function options(): array
    {
        return ['module' => 'Module whose folder holds it (required)', 'model' => 'Eloquent model to derive fields from'];
    }

    protected function files(GenerationContext $context): array
    {
        $class = $this->kit->names->className($context->name);
        [$folder] = self::Parts[$this->part];

        if ($class === $folder) {
            throw GenerationException::invalid(sprintf('"%s" cannot be named after its folder.', $class));
        }

        $directory = $this->moduleDirectory($context->required('module', sprintf('the module whose %s/ folder holds the %s.', $folder, $this->part)));
        $model = $this->model($context, false, '');
        $fields = [];

        if ($model !== null) {
            $inspection = $this->kit->models->inspect($model);
            array_push($this->warnings, ...$inspection['warnings']);
            $fields = $inspection['fields'];
        }

        if ($fields === []) {
            $fields = [new ModelField('name', 'string', false, true)];
            $this->warn(sprintf('No model fields were available: the %s declares a sample "name" field; replace it.', $this->part));
        }

        $code = match ($this->part) {
            'form' => $this->kit->fields->form($fields, 1),
            'table' => $this->kit->fields->table($fields, 1),
            default => $this->kit->fields->infolist($fields, 1),
        };

        if (in_array($code['code'], ['new Form', 'new Table', 'new Infolist'], true)) {
            throw GenerationException::invalid(sprintf('The model has no attributes a %s can show (sensitive or unfillable attributes are left out).', $this->part));
        }

        $relative = $folder.'/'.$class.'.php';
        $this->next(sprintf("Use it in a Resource of this module: require dirname(__DIR__).'/%s', (from Resources/) or require __DIR__.'/%s', (from Module.php).", $relative, $relative));

        return [$this->file($context, $directory.'/'.$relative, $this->part, [
            'imports' => $this->imports($code['imports'], $code['code']),
            $this->part => StubValue::code($code['code']),
            'relative' => StubValue::string($relative),
        ], ucfirst($this->part).' "'.$class.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return [$this->part === 'infolist' ? 'component' : $this->part, $this->kit->files->relative($this->moduleDirectory((string) $context->string('module'))).'/'.self::Parts[$this->part][0].'/'.$this->kit->names->className($context->name).'.php'];
    }
}
