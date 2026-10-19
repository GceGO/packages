<?php

declare(strict_types=1);

namespace Gcego\Settings\Livewire;

use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\FormManager;
use Gcego\Forms\Rendering\FormRenderContext;
use Gcego\Forms\Rendering\FormRenderFrame;
use Gcego\Forms\State\FormState;
use Gcego\Panels\PanelManager;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\Exceptions\SettingsException;
use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Integration\FormSettingsAdapter;
use Gcego\Settings\Pages\SettingsPageResolver;
use Gcego\Settings\Pages\SettingsScreen;
use Gcego\Settings\Resolution\ResolvedSetting;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\Setting;
use Gcego\Settings\Support\SettingsStyles;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Writing\SettingsWriter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * <livewire:gcego-settings-form page="general" />: the form of one settings page.
 *
 * Lifecycle, on every request: the page and its scope are resolved again
 * from the server (the request's verified tenant, panel and user) and must
 * still be the scope this component was opened for, or the request is
 * refused (403); the viewer's rights are checked again. Saving sends only
 * the values that changed, with the versions this form loaded, through
 * SettingsWriter (authorized, validated, atomic); reset removes the
 * override at this scope.
 *
 * Public state: the page name, the scope identity, versions and the
 * loaded values are #[Locked]; $data is untrusted and may only address
 * the page's own settings. Sensitive values are never part of any state:
 * their fields are always empty, and only "a value is set" is known.
 */
final class SettingsForm extends Component
{
    #[Locked]
    public string $page = '';

    /** The scope this form edits (ScopeContext::identity()), fixed at mount. */
    #[Locked]
    public string $scope = '';

    /** @var array<string, int> */
    #[Locked]
    public array $versions = [];

    /** @var array<string, mixed> Values as loaded (non-sensitive settings only). */
    #[Locked]
    public array $original = [];

    /** @var array<string, bool> Whether each sensitive setting has a value. */
    #[Locked]
    public array $secrets = [];

    /** @var array{status: string, message: string}|null */
    #[Locked]
    public ?array $feedback = null;

    /**
     * Untrusted form state.
     *
     * @var array<array-key, mixed>
     */
    public array $data = [];

    public function mount(string $page): void
    {
        $this->page = $page;
        $screen = $this->screen(false);
        $this->scope = $screen->context->identity();
        $this->load($screen);
    }

    /**
     * Every client update must address a visible setting of this page.
     */
    public function updating(string $property, mixed $value): void
    {
        if ($property !== 'data' && ! str_starts_with($property, 'data.')) {
            return;
        }

        $key = substr($property, 5);

        if ($property === 'data' || ! in_array($key, $this->screen()->keys(), true)) {
            throw InvalidStateException::unknownPath($property);
        }
    }

    public function save(): void
    {
        $screen = $this->screen();
        $this->resetErrorBag();
        $this->feedback = null;
        $definitions = SettingsPageResolver::byKey($screen->visible);

        try {
            $submitted = app(FormManager::class)->process(app(FormSettingsAdapter::class)->form($screen), $this->submitted($screen));
        } catch (ValidationException $exception) {
            $this->showErrors($exception->errors());

            return;
        }

        $changed = [];

        foreach ($submitted as $key => $value) {
            $definition = $definitions[$key] ?? null;

            if ($definition === null || ! $screen->canEdit($key)) {
                continue;
            }

            $value = app(FormSettingsAdapter::class)->value($definition, $value);

            if ($definition->sensitive ? $value !== null && $value !== '' : ! $this->unchanged($definition, $value)) {
                $changed[$key] = $value;
            }
        }

        if ($changed === []) {
            $this->feedback = ['status' => 'info', 'message' => (string) __('gcego-settings::messages.nothing_changed')];

            return;
        }

        try {
            $result = app(SettingsWriter::class)->write($screen->context, $changed, SettingsActor::user($this->viewer()), array_intersect_key($this->versions, $changed));
        } catch (SettingsException $exception) {
            $this->refuse($exception);

            return;
        }

        $this->load($this->screen());
        $this->feedback = ['status' => 'success', 'message' => $result->pending === []
            ? (string) __('gcego-settings::messages.saved')
            : (string) trans_choice('gcego-settings::messages.saved_pending', count($result->pending), ['count' => count($result->pending)])];
        $this->dispatch('gcego-settings-saved', page: $this->page);
    }

    /**
     * Remove this scope's override of one setting (it inherits again).
     */
    public function resetSetting(mixed $key): void
    {
        $screen = $this->screen();
        $this->resetErrorBag();
        $this->feedback = null;

        if (! is_string($key) || ! in_array($key, $screen->keys(), true) || ! $screen->canEdit($key)) {
            $this->addError('settings', (string) __('gcego-settings::messages.errors.forbidden'));

            return;
        }

        try {
            app(SettingsWriter::class)->reset($screen->context, [$key], SettingsActor::user($this->viewer()), [$key => $this->versions[$key] ?? 0]);
        } catch (SettingsException $exception) {
            $this->refuse($exception, $key);

            return;
        }

        $this->load($this->screen());
        $this->feedback = ['status' => 'success', 'message' => (string) __('gcego-settings::messages.reset_done', ['setting' => SettingsPageResolver::byKey($screen->visible)[$key]->label()])];
        $this->dispatch('gcego-settings-saved', page: $this->page);
    }

    public function render(): View
    {
        $screen = $this->screen();
        $resolved = app(SettingsReader::class)->resolveMany($screen->visible, $screen->context);
        $styles = app(SettingsStyles::class);
        $errors = [];
        $bag = $this->getErrorBag();

        foreach ($bag instanceof MessageBag ? $bag->toArray() : [] as $key => $messages) {
            $errors[(string) preg_replace('/^data\./', '', (string) $key)] = array_values(array_filter((array) $messages, is_string(...)));
        }

        $sections = [];
        $definitions = SettingsPageResolver::byKey($screen->visible);

        foreach ($screen->group->children() as $child) {
            $settings = $child instanceof SettingsSection ? $child->settings() : ($child instanceof Setting ? [$child] : []);
            $rows = [];

            foreach ($settings as $setting) {
                if (isset($definitions[$setting->key()])) {
                    $rows[] = $this->row($screen, $definitions[$setting->key()], $resolved[$setting->key()], $errors);
                }
            }

            if ($rows === []) {
                continue;
            }

            if ($child instanceof SettingsSection) {
                $sections[] = ['name' => $child->nodeName(), 'title' => (string) __($child->label()), 'description' => $child->description() === null ? null : (string) __($child->description()), 'columns' => $child->columns(), 'rows' => $rows];
            } elseif ($sections !== [] && $sections[array_key_last($sections)]['name'] === null) {
                $sections[array_key_last($sections)]['rows'][] = $rows[0];
            } else {
                $sections[] = ['name' => null, 'title' => (string) __($screen->group->title()), 'description' => null, 'columns' => 1, 'rows' => $rows];
            }
        }

        return view('gcego-settings::livewire.form', [
            'screen' => $screen,
            'sections' => $sections,
            'styles' => $styles,
            'general' => $errors['settings'] ?? [],
            'editable' => in_array(true, $screen->editable, true),
        ]);
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return array{key: string, label: string, html: HtmlString, span: int, source: string, overridden: bool, inherited: string|null, sensitive: bool, has_secret: bool, can_reset: bool}
     */
    private function row(SettingsScreen $screen, SettingDefinition $definition, ResolvedSetting $resolved, array $errors): array
    {
        $forms = app(FormManager::class);
        $form = app(FormSettingsAdapter::class)->single($definition, $screen->canEdit($definition->key), $this->secrets[$definition->key] ?? false);
        $schema = $forms->schema($form);
        $frame = new FormRenderFrame($schema, new FormState($this->data), $forms->access($schema), 'data', $errors, idPrefix: 'gcego-settings-'.$this->page);
        $html = app(FormRenderContext::class)->run($frame, static fn (): string => app(Runtime::class)->render($form, RenderMode::Livewire, identity: 'settings-page:'.$screen->page->pageName())->toHtml());

        return [
            'key' => $definition->key,
            'label' => $definition->label(),
            'html' => new HtmlString($html),
            'span' => $definition->setting->span(),
            'source' => $resolved->isOverridden() ? 'override' : ($resolved->source === null ? 'default' : 'inherited'),
            'overridden' => $resolved->isOverridden(),
            'inherited' => $resolved->source?->label(),
            'sensitive' => $definition->sensitive,
            'has_secret' => $this->secrets[$definition->key] ?? false,
            'can_reset' => $resolved->isOverridden() && $screen->canEdit($definition->key),
        ];
    }

    private function load(SettingsScreen $screen): void
    {
        $adapter = app(FormSettingsAdapter::class);
        $resolved = app(SettingsReader::class)->resolveMany($screen->visible, $screen->context);
        $this->data = $this->original = $this->versions = $this->secrets = [];

        foreach ($screen->visible as $definition) {
            $value = $resolved[$definition->key];
            $this->data[$definition->key] = $adapter->hydrate($definition, $value);
            $this->versions[$definition->key] = $value->version;

            if ($definition->sensitive) {
                $this->secrets[$definition->key] = $value->hasValue();
            } else {
                $this->original[$definition->key] = $this->data[$definition->key];
            }
        }
    }

    /**
     * The page, from the server alone, still at the scope it was opened for.
     */
    private function screen(bool $verify = true): SettingsScreen
    {
        $viewer = $this->viewer();

        try {
            $screen = app(SettingsPageResolver::class)->open($this->page, $viewer, $this->panel());
        } catch (SettingsException) {
            abort(404);
        }

        if ($verify && ! hash_equals($this->scope, $screen->context->identity())) {
            abort(403);
        }

        return $screen;
    }

    /**
     * Submitted values of the page's settings only (the form refuses anything else).
     *
     * @return array<string, mixed>
     */
    private function submitted(SettingsScreen $screen): array
    {
        $values = [];

        foreach ($this->data as $key => $value) {
            if (! in_array($key, $screen->keys(), true)) {
                throw InvalidStateException::unknownPath('data.'.$key);
            }

            $values[(string) $key] = $value;
        }

        return $values;
    }

    private function unchanged(SettingDefinition $definition, mixed $value): bool
    {
        $original = $this->original[$definition->key] ?? null;

        if ($value === $original) {
            return true;
        }

        try {
            $caster = new SettingCaster;

            return $caster->same($definition->type, $caster->cast($definition->type, $value, true, $definition->enum), $caster->cast($definition->type, $original, true, $definition->enum));
        } catch (InvalidSettingException) {
            return false;
        }
    }

    /**
     * @param  array<array-key, mixed>  $errors
     */
    private function showErrors(array $errors): void
    {
        foreach ($errors as $key => $messages) {
            foreach (is_array($messages) ? $messages : [$messages] as $message) {
                if (is_string($message)) {
                    $this->addError(in_array((string) $key, ['settings'], true) ? 'settings' : 'data.'.$key, $message);
                }
            }
        }
    }

    private function refuse(SettingsException $exception, ?string $key = null): void
    {
        match (true) {
            $exception instanceof InvalidSettingException && $exception->errors() !== [] => $this->showErrors($exception->errors()),
            $exception instanceof SettingConflictException => $this->addError('settings', (string) __('gcego-settings::messages.errors.stale')),
            $exception instanceof SettingAuthorizationException => $this->addError('settings', (string) __('gcego-settings::messages.errors.forbidden')),
            $exception instanceof SettingScopeException => $this->addError('settings', (string) __('gcego-settings::messages.errors.scope')),
            default => $this->addError($key === null ? 'settings' : 'data.'.$key, (string) __('gcego-settings::messages.errors.failed')),
        };
    }

    private function viewer(): Authenticatable
    {
        $user = auth()->user();

        return $user instanceof Authenticatable ? $user : abort(403);
    }

    private function panel(): ?string
    {
        try {
            return app()->bound(PanelManager::class) ? app(PanelManager::class)->current()?->name() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
