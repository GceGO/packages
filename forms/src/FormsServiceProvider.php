<?php

declare(strict_types=1);

namespace Gcego\Forms;

use Gcego\Actions\ActionManager;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Forms\Diagnostics\FormInspector;
use Gcego\Forms\Fields\Checkbox;
use Gcego\Forms\Fields\Field;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Integration\FormInputResolver;
use Gcego\Forms\Layout\LayoutComponent;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Forms\Options\OptionsResolver;
use Gcego\Forms\Presets\FormPresets;
use Gcego\Forms\Reactive\ConditionEvaluator;
use Gcego\Forms\Reactive\FormEvaluation;
use Gcego\Forms\Rendering\FieldRenderer;
use Gcego\Forms\Rendering\FormRenderContext;
use Gcego\Forms\Rendering\FormRenderer;
use Gcego\Forms\Rendering\LayoutRenderer;
use Gcego\Forms\State\StateDehydrator;
use Gcego\Forms\State\StateHydrator;
use Gcego\Forms\Validation\ValidationEngine;
use Gcego\Rendering\Renderers\RendererRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/forms; also the "gcego/forms" GCEGO package.
 *
 * Singletons hold configuration and compiled schemas (immutable definitions);
 * services touching the current user, request or render are scoped. Booting
 * registers renderers, the Livewire host, views, translations, styling slots
 * and the gcego/actions input resolver; it never queries or loads definitions.
 */
final class FormsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-forms';

    public const string LivewireAlias = 'gcego-form';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-forms.php', 'gcego-forms');

        $this->app->singleton(FormsConfig::class, static function (Application $app): FormsConfig {
            $config = $app->make(Repository::class)->get('gcego-forms', []);

            return FormsConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(FormManager::class);
        $this->app->singleton(ConditionEvaluator::class);
        $this->app->singleton(StateHydrator::class);
        $this->app->scoped(FormRenderContext::class);
        $this->app->scoped(FormEvaluation::class);
        $this->app->scoped(OptionsResolver::class);
        $this->app->scoped(ValidationEngine::class);
        $this->app->scoped(StateDehydrator::class);
        $this->app->scoped(FormInspector::class);
        $this->app->singleton(FormInputResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', self::Views);
        $this->loadTranslationsFrom(dirname(__DIR__).'/resources/lang', self::Views);
        $this->publishes([dirname(__DIR__).'/config/gcego-forms.php' => $this->app->configPath('gcego-forms.php')], 'gcego-forms-config');
        $this->publishes([dirname(__DIR__).'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-forms-views');

        $this->app->make(FormsConfig::class);

        Livewire::component(self::LivewireAlias, FormHost::class);

        $renderers = $this->app->make(RendererRegistry::class);
        $renderers->register(Form::class, FormRenderer::class);
        $renderers->register(Field::class, FieldRenderer::class);
        $renderers->register(LayoutComponent::class, LayoutRenderer::class);

        $this->app->make(ActionManager::class)->inputResolver($this->app->make(FormInputResolver::class));

        $styling = $this->app->make(ChromaManager::class);
        $styling->defineSlots(Field::class, [
            new SlotDefinition('root', classes: FormPresets::FieldRoot),
            new SlotDefinition('label', classes: FormPresets::Label),
            new SlotDefinition(Field::Input, classes: FormPresets::Input),
            new SlotDefinition(Field::Helper, classes: FormPresets::Helper),
            new SlotDefinition('hint', classes: FormPresets::Hint),
            new SlotDefinition(Field::Error, classes: FormPresets::Error),
        ]);
        $styling->defineSlots(Checkbox::class, [
            new SlotDefinition('root', classes: FormPresets::FieldRoot),
            new SlotDefinition('label', classes: FormPresets::Label),
            new SlotDefinition(Field::Helper, classes: FormPresets::Helper),
            new SlotDefinition('hint', classes: FormPresets::Hint),
            new SlotDefinition(Field::Error, classes: FormPresets::Error),
        ]);
        $styling->defineSlots(Repeater::class, [
            new SlotDefinition('root', classes: FormPresets::Group),
            new SlotDefinition('label', classes: FormPresets::Label),
            new SlotDefinition(Repeater::Item),
            new SlotDefinition(Field::Helper, classes: FormPresets::Helper),
            new SlotDefinition('hint', classes: FormPresets::Hint),
            new SlotDefinition(Field::Error, classes: FormPresets::Error),
        ]);
        $styling->defineSlots(Section::class, [
            new SlotDefinition('root', classes: FormPresets::Section),
            new SlotDefinition('heading', classes: FormPresets::SectionHeading),
            new SlotDefinition('header', classes: FormPresets::SectionHeader),
            new SlotDefinition('description', classes: FormPresets::SectionDescription),
            new SlotDefinition('body', classes: FormPresets::SectionBody),
        ]);
        $styling->defineSlots(LayoutComponent::class, [new SlotDefinition('root')]);
        $styling->defineSlots(Form::class, [new SlotDefinition('root')]);
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/forms',
            requires: ['gcego/core', 'gcego/rendering', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions'],
            capabilities: ['forms', 'forms.livewire', 'forms.validation', 'forms.repeaters', 'forms.uploads'],
            description: 'Declarative forms with secure validation, dehydration, reactive state and Livewire hosting.',
        );
    }
}
