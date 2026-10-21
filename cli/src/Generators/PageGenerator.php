<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-page Reports [--type=page|dashboard] [--title=] [--ability=]
 * [--panel=]: a gcego/pages page in app/GCEGo/Pages. Without --ability it
 * requires authentication (pages are deny-by-default).
 */
final class PageGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'page';
    }

    public function description(): string
    {
        return 'A page (gcego/pages), routed through its panels';
    }

    public function options(): array
    {
        return ['type' => 'page or dashboard', 'title' => 'Title', 'ability' => 'Gate ability required to open it (default: signed in)', 'panel' => 'Panel to check it is listed in'];
    }

    protected function files(GenerationContext $context): array
    {
        $name = $this->kit->names->slug($context->name);
        $type = $context->string('type') ?? 'page';
        $class = match ($type) {
            'page' => ['Page', 'Gcego\\Pages\\Page'],
            'dashboard' => ['DashboardPage', 'Gcego\\Pages\\Types\\DashboardPage'],
            default => throw GenerationException::invalid(sprintf('--type must be "page" or "dashboard", not "%s".', $type)),
        };

        $this->assertAvailable('page:'.$name, $this->kit->paths->directory('page').'/'.$name.'.php');

        $requirement = $this->requirement($context);
        $panel = $this->assertPanel($context->string('panel'));

        if ($panel !== null) {
            $this->next(sprintf("Add new PageReference('%s') to panel \"%s\" to route it there (pages are routed by their panels).", $name, $panel));
        }

        if ($context->string('ability') !== null) {
            $this->next(sprintf('Define the "%s" ability (Gate::define) — until then nobody can open the page.', (string) $context->string('ability')));
        }

        return [$this->file($context, $this->kit->paths->directory('page').'/'.$name.'.php', 'page', [
            'imports' => $this->imports(['Gcego\\Components\\Properties\\Property', 'Gcego\\Layouts\\Components\\Section', 'Gcego\\Pages\\Components\\Content', $class[1], $requirement['import']]),
            'page_class' => StubValue::identifier($class[0]),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $this->kit->names->title($context->name)),
            'requirement' => StubValue::code($requirement['code']),
        ], 'Page "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['page', $this->kit->names->slug($context->name)];
    }
}
