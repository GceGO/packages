<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-module Academics [--title=] [--description=] [--icon=]
 * [--navigation-group=] [--panel=]: app/GCEGo/Modules/Academics/Module.php,
 * which includes every Resources/*.php file (gcego:make-resource writes them).
 */
final class ModuleGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'module';
    }

    public function description(): string
    {
        return 'A module (gcego/modules) that includes its Resources/ folder';
    }

    public function options(): array
    {
        return ['title' => 'Title', 'description' => 'Description', 'icon' => 'Icon name', 'navigation-group' => 'Navigation group', 'panel' => 'Panel that should list it'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $class = $names->className($context->name);
        $name = $names->slug($context->name);

        $this->assertAvailable('module:'.$name, $this->kit->paths->directory('module').'/'.$class.'/Module.php');

        $panel = $this->assertPanel($context->string('panel'));
        $properties = "\n    new Property(Module::Navigation, true),";

        foreach (['description' => 'Description', 'icon' => 'Icon', 'navigation-group' => 'NavigationGroup'] as $option => $constant) {
            if (($value = $context->string($option)) !== null) {
                if ($option === 'icon' && preg_match('#^[a-z0-9][a-z0-9._:/-]*$#', $value) !== 1) {
                    throw GenerationException::invalid(sprintf('--icon "%s" is not a valid icon name.', $value));
                }

                $properties .= sprintf("\n    new Property(Module::%s, '%s'),", $constant, StubValue::string($value)->rendered);
            }
        }

        if ($panel !== null) {
            $this->next(sprintf("Add new ModuleReference('%s') to panel \"%s\" (app/GCEGo/Panels/%s.php) to list it there.", $name, $panel, $panel));
        }

        $this->next(sprintf('Add resources: php artisan gcego:make-resource Subject --module=%s --model="App\\Models\\Subject"', $name));

        return [$this->file($context, $this->kit->paths->directory('module').'/'.$class.'/Module.php', 'module', [
            'imports' => $this->imports(['Gcego\\Components\\Property', 'Gcego\\Layouts\\Components\\Section', 'Gcego\\Modules\\Module', 'Gcego\\Pages\\Components\\Content', 'Gcego\\Pages\\Types\\DashboardPage', 'Gcego\\Security\\Requirements\\Authenticated']),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $names->title($context->name)),
            'properties' => StubValue::code($properties),
        ], 'Module "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['module', $this->kit->names->slug($context->name)];
    }
}
