<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-layout AcademicDashboard [--columns=2] [--ability=]: a
 * gcego/layouts layout (a section with a responsive grid) in app/GCEGo/Layouts.
 */
final class LayoutGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'layout';
    }

    public function description(): string
    {
        return 'A layout (gcego/layouts): section and responsive grid';
    }

    public function options(): array
    {
        return ['columns' => 'Grid columns, 1 to 4 (default 2)', 'ability' => 'Gate ability to see it (default: signed in)', 'title' => 'Label'];
    }

    protected function files(GenerationContext $context): array
    {
        $name = $this->kit->names->slug($context->name);
        $columns = $context->string('columns') ?? '2';

        if (preg_match('/^[1-4]$/', $columns) !== 1) {
            throw GenerationException::invalid('--columns must be 1, 2, 3 or 4.');
        }

        $this->assertAvailable('layout:'.$name, $this->kit->paths->directory('layout').'/'.$name.'.php');

        $requirement = $this->requirement($context);

        return [$this->file($context, $this->kit->paths->directory('layout').'/'.$name.'.php', 'layout', [
            'imports' => $this->imports(['Gcego\\Components\\Property', 'Gcego\\Layouts\\Components\\Grid', 'Gcego\\Layouts\\Components\\Section', 'Gcego\\Layouts\\Configuration\\Columns', 'Gcego\\Layouts\\Layout', 'Gcego\\Pages\\Components\\Content', $requirement['import']]),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $this->kit->names->title($context->name)),
            'columns' => StubValue::code($columns),
            'requirement' => StubValue::code($requirement['code']),
        ], 'Layout "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['component', 'layout:'.$this->kit->names->slug($context->name)];
    }
}
