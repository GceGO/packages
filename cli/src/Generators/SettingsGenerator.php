<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;
use Illuminate\Support\Str;

/**
 * gcego:make-settings AcademicSettings [--scope=global|panel|tenant|user]
 * [--ability=] [--page]: a gcego/settings group (two typed settings with
 * defaults and validation rules), and with --page its settings page. Writes
 * require the "<name>.manage" ability (or --ability).
 */
final class SettingsGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'settings';
    }

    public function description(): string
    {
        return 'A settings group (gcego/settings), optionally with its page';
    }

    public function requires(): array
    {
        return ['gcego/settings'];
    }

    public function options(): array
    {
        return ['scope' => 'global, panel, tenant or user (default global)', 'ability' => 'Ability to change them (default <name>.manage)', 'page' => 'Also generate a settings page', 'title' => 'Title'];
    }

    protected function files(GenerationContext $context): array
    {
        $base = (string) preg_replace('/Settings$/', '', $this->kit->names->className($context->name)) ?: $this->kit->names->className($context->name);
        $name = Str::kebab($base);
        $key = Str::snake($base);
        $scope = $context->string('scope') ?? 'global';

        if (! in_array($scope, ['global', 'panel', 'tenant', 'user'], true)) {
            throw GenerationException::invalid('--scope must be global, panel, tenant or user.');
        }

        $this->assertAvailable('settings:'.$name, $this->kit->paths->directory('settings').'/'.$name.'.php');

        $title = $context->string('title') ?? Str::headline($base);
        $requirement = $this->requirement($context, $name.'.manage');
        $files = [$this->file($context, $this->kit->paths->directory('settings').'/'.$name.'.php', 'settings', [
            'imports' => $this->imports(['Gcego\\Settings\\Setting', 'Gcego\\Settings\\SettingsGroup', $requirement['import']]),
            'name' => StubValue::string($name),
            'title' => StubValue::string($title),
            'key' => StubValue::string($key),
            'requirement' => StubValue::code($requirement['code']),
            'scopes' => StubValue::code($scope === 'global' ? '' : sprintf("\n    scopes: ['%s'],", $scope)),
        ], 'Settings group "'.$name.'"')];

        if ($context->string('ability') === null) {
            $this->next(sprintf("Define who may change them: Gate::define('%s.manage', ...) (or holders of gcego-settings.manage).", $name));
        }

        if ($context->flag('page')) {
            $imports = ['Gcego\\Settings\\SettingsPage'];

            if ($scope !== 'global') {
                $imports[] = 'Gcego\\Settings\\Scopes\\SettingScope';
            }

            $files[] = $this->file($context, $this->kit->paths->directory('settings-page').'/'.$name.'.php', 'settings-page', [
                'imports' => $this->imports($imports),
                'name' => StubValue::string($name),
                'title' => StubValue::string($title.' Settings'),
                'group' => StubValue::string($name),
                'scope' => StubValue::code($scope === 'global' ? '' : ', scope: SettingScope::'.ucfirst($scope)),
            ], 'Settings page "'.$name.'"');
        }

        return $files;
    }

    protected function test(GenerationContext $context): array
    {
        return ['settings', Str::kebab((string) preg_replace('/Settings$/', '', $this->kit->names->className($context->name)) ?: $context->name)];
    }
}
