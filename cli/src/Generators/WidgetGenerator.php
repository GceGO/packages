<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Blueprints\Blueprint;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-widget StudentStats --type=stat|trend|chart|progress|recent|custom
 * [--model=] [--ability=]: a gcego/widgets widget in app/GCEGo/Widgets.
 * Model widgets aggregate through gcego/data (tenant scoping applies) and
 * require the model's "viewAny" ability; a recent-records widget lists a
 * blueprint's records (--blueprint; its authorization applies); a custom
 * widget gets a data provider class and needs an explicit --ability.
 */
final class WidgetGenerator extends AbstractGenerator
{
    public const array Types = ['stat', 'trend', 'chart', 'progress', 'recent', 'custom'];

    public function id(): string
    {
        return 'widget';
    }

    public function description(): string
    {
        return 'A dashboard widget (gcego/widgets): stat, trend, chart, progress, recent records or custom';
    }

    public function options(): array
    {
        return ['type' => implode(', ', self::Types), 'model' => 'Eloquent model to aggregate (stat, trend, chart, progress)', 'blueprint' => 'Blueprint whose records a recent widget lists', 'ability' => 'Gate ability to see it (custom: required)', 'title' => 'Label'];
    }

    protected function files(GenerationContext $context): array
    {
        $names = $this->kit->names;
        $class = $names->className($context->name);
        $name = $names->snake($context->name);
        $type = $context->string('type') ?? 'stat';
        $title = $context->string('title') ?? $names->title($context->name);

        if (! in_array($type, self::Types, true)) {
            throw GenerationException::invalid(sprintf('--type must be one of %s.', implode(', ', self::Types)));
        }

        $this->assertAvailable('widget:'.$name, $this->kit->paths->directory('widget').'/'.$class.'.php');

        $label = StubValue::string($title)->rendered;
        $files = [];

        if ($type === 'recent') {
            $blueprint = $this->kit->names->identifier($context->required('blueprint', 'the blueprint whose newest records it lists (its authorization and tenant scope apply).'), 'blueprint');
            $definition = $this->kit->catalog->definition('blueprint:'.$blueprint);

            if (! $definition instanceof Blueprint) {
                throw GenerationException::missing(sprintf('The blueprint "%s" does not exist (php artisan gcego:make-blueprint %s --model=...).', $blueprint, $blueprint));
            }

            $model = $this->model($context, false, '');
            $titleColumn = $model === null ? 'name' : ($this->kit->models->inspect($model)['title'] ?? (new $model)->getKeyName());
            $requirement = $this->requirement($context);
            $imports = ['Gcego\\Components\\Properties\\Property', 'Gcego\\Widgets\\Records\\RecentRecords', $requirement['import']];
            $widget = sprintf("new RecentRecords(\n    '%s',\n    new Property(RecentRecords::Name, '%s'),\n    new Property(RecentRecords::Blueprint, '%s'),\n    new Property(RecentRecords::TitleColumn, '%s'),\n    %s,\n)", $label, $name, $blueprint, $titleColumn, $requirement['code']);
            $this->next(sprintf('Records come through blueprint "%s": its requirements, policies and tenant scope apply.', $blueprint));
        } elseif ($type === 'custom') {
            if ($context->string('ability') === null) {
                throw GenerationException::invalid('A custom widget needs --ability: who may see it cannot be guessed.');
            }

            $directory = $this->kit->paths->directory('support').'/Widgets';
            $provider = $class.'Data';
            $namespace = $this->kit->namespaces->forDirectory($directory);
            $requirement = $this->requirement($context);
            $imports = ['Gcego\\Components\\Properties\\Property', 'Gcego\\Widgets\\Data\\DataProvider', 'Gcego\\Widgets\\Statistics\\Stat', $requirement['import'], $namespace.'\\'.$provider];
            $widget = sprintf("new Stat(\n    '%s',\n    new Property(Stat::Name, '%s'),\n    new DataProvider(%s::class),\n    %s,\n)", $label, $name, $provider, $requirement['code']);
            $files[] = $this->file($context, $directory.'/'.$provider.'.php', 'widget-data', ['namespace' => StubValue::identifier($namespace), 'class' => StubValue::identifier($provider), 'name' => StubValue::string($name)], 'Data provider of widget "'.$name.'"', $namespace, $namespace.'\\'.$provider);
            $this->next(sprintf('Compute the value in %s::resolve().', $provider));
        } else {
            $model = $this->model($context, true, sprintf('the model the %s widget aggregates, e.g. --model="App\\Models\\Student".', $type));
            assert($model !== null);
            $short = class_basename($model);
            $requirement = $this->requirement($context, 'viewAny', $model);
            $inspection = in_array($type, ['trend', 'chart'], true) ? $this->kit->models->inspect($model) : null;
            $timestamps = (new $model)->usesTimestamps();

            if ($inspection !== null && ! $timestamps) {
                throw GenerationException::invalid(sprintf('A %s widget needs a date column: %s does not use timestamps (created_at).', $type, $short));
            }

            $imports = ['Gcego\\Components\\Properties\\Property', 'Gcego\\Widgets\\Data\\Aggregate', $requirement['import'], $model];
            [$widget, $more] = match ($type) {
                'stat' => [sprintf("new Stat(\n    '%s',\n    new Property(Stat::Name, '%s'),\n    new Aggregate(%s::class),\n    %s,\n)", $label, $name, $short, $requirement['code']), ['Gcego\\Widgets\\Statistics\\Stat']],
                'trend' => [sprintf("new Stat(\n    '%s',\n    new Property(Stat::Name, '%s'),\n    new Aggregate(%s::class, date: 'created_at', compare: true),\n    new Trend,\n    new DateRange('last_30_days'),\n    %s,\n)", $label, $name, $short, $requirement['code']), ['Gcego\\Widgets\\Statistics\\Stat', 'Gcego\\Widgets\\Configuration\\Trend', 'Gcego\\Widgets\\Data\\DateRange']],
                'chart' => [sprintf("new LineChart(\n    new Property(LineChart::Name, '%s'),\n    new Property(LineChart::Label, '%s'),\n    new Aggregate(%s::class, date: 'created_at', bucket: 'day'),\n    new DateRange('last_30_days'),\n    %s,\n)", $name, $label, $short, $requirement['code']), ['Gcego\\Widgets\\Charts\\LineChart', 'Gcego\\Widgets\\Data\\DateRange']],
                default => [sprintf("new Progress(\n    '%s',\n    new Property(Progress::Name, '%s'),\n    new Property(Progress::Maximum, 100),\n    new Aggregate(%s::class),\n    %s,\n)", $label, $name, $short, $requirement['code']), ['Gcego\\Widgets\\Progress\\Progress']],
            };
            $imports = [...$imports, ...$more];

            if ($context->string('ability') === null) {
                $this->next(sprintf('The widget shows to users whose %s policy allows viewAny.', $short));
            }
        }

        $this->next(sprintf('Place it on a dashboard or layout as widget:%s (gcego/widgets), e.g. in a DashboardPage.', $name));
        $files[] = $this->file($context, $this->kit->paths->directory('widget').'/'.$class.'.php', 'widget', [
            'imports' => $this->imports($imports, $widget),
            'name' => StubValue::string($name),
            'widget' => StubValue::code($widget),
        ], 'Widget "'.$name.'"');

        return $files;
    }

    protected function test(GenerationContext $context): array
    {
        return ['component', 'widget:'.$this->kit->names->snake($context->name)];
    }
}
