<?php

declare(strict_types=1);

namespace Gcego\Widgets\Livewire;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Widgets\Exceptions\InvalidWidgetStateException;
use Gcego\Widgets\State\WidgetState;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * <livewire:gcego-widget widget="enrolment" />
 * <livewire:gcego-widget widget="student_summary" :record="$student->id" />
 * <livewire:gcego-widget widget="layout:dashboard" path="dashboard.#0.enrolment" />   (embedded; mounted by the renderer)
 *
 * Hosts one registered widget when it needs interaction: a date range,
 * refresh (button, polling, events) or feed pages. The widget identity and
 * record are #[Locked]; the range and its dates are untrusted and validated
 * against the widget's DateRange on every request; every request
 * re-resolves the widget from the registry and re-checks its requirements,
 * and data is read again as the current user. An embedded widget is
 * identified by its registered owner and its #[Locked] path in the owner,
 * and inherits the owner's requirements. The snapshot holds only
 * those small values, never data or models.
 */
final class WidgetHost extends Component
{
    #[Locked]
    public string $widget = '';

    #[Locked]
    public string|int|null $record = null;

    public string $range = '';

    public string $from = '';

    public string $to = '';

    #[Locked]
    public ?string $path = null;

    #[Locked]
    public int $page = 1;

    #[Locked]
    public ?string $notice = null;

    public function mount(string $widget, string|int|null $record = null, ?string $range = null, ?string $path = null): void
    {
        $widgets = app(WidgetManager::class);
        $this->path = $path;
        $this->widget = $path === null ? $widgets->identity($widget) : $widget;
        [, $definition] = $this->authorized();

        $this->record = $record;
        $this->range = $range ?? $definition->dateRange()->default ?? '';
        $this->state($definition);
    }

    /**
     * Browser events that refresh this widget (from its Refresh node only).
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        try {
            $refresh = $this->definition()[1]->refresh();
        } catch (\Throwable) {
            return [];
        }

        return $refresh === null ? [] : array_fill_keys($refresh->on, 'refresh');
    }

    public function refresh(): void
    {
        $this->notice = null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['range', 'from', 'to'], true)) {
            $this->page = 1;
        }
    }

    public function olderPage(): void
    {
        $this->page = min(WidgetState::MaxPage, $this->page + 1);
    }

    public function newerPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function render(): View
    {
        $widgets = app(WidgetManager::class);
        [$owner, $definition] = $this->authorized();
        $state = $this->state($definition);
        $refresh = $definition->refresh();
        $id = $this->getId();
        $range = $definition->dateRange();

        return view('gcego-widgets::widgets.host', [
            'content' => $widgets->renderWidget($this->widget, $definition, $state, $this->record, is_string($id) ? $id : null, live: true, owner: $owner, path: $this->path),
            'poll' => $refresh?->every === null ? null : max($refresh->every, $widgets->config()->minimumRefreshSeconds),
            'presets' => $range === null ? [] : $range->presets,
            'custom' => $range !== null && $range->custom,
            'hasRange' => $range !== null,
            'period' => $state->period,
            'title' => $definition->title(),
        ]);
    }

    /**
     * The hosted widget, re-resolved from the registry, and its owner when embedded.
     *
     * @return array{0: ComponentInterface|null, 1: Widget}
     */
    private function definition(): array
    {
        $widgets = app(WidgetManager::class);

        return $this->path === null ? [null, $widgets->resolve($this->widget)] : $widgets->locate($this->widget, $this->path);
    }

    /**
     * @return array{0: ComponentInterface|null, 1: Widget}
     */
    private function authorized(): array
    {
        [$owner, $definition] = $this->definition();
        app(WidgetManager::class)->authorize($owner ?? $definition, $this->path);

        return [$owner, $definition];
    }

    private function state(Widget $definition): WidgetState
    {
        $widgets = app(WidgetManager::class);
        $awaitingDates = $this->range === 'custom' && ($this->from === '' || $this->to === '') && ($definition->dateRange()->custom ?? false);
        // While a custom range waits for both dates, the default period stays in effect.
        $input = ['range' => $this->range === '' || $awaitingDates ? null : $this->range, 'from' => $this->from === '' ? null : $this->from, 'to' => $this->to === '' ? null : $this->to, 'page' => $this->page];

        try {
            return $widgets->state($definition, $input);
        } catch (InvalidWidgetStateException) {
            $this->range = $definition->dateRange()->default ?? '';
            $this->from = '';
            $this->to = '';
            $this->page = 1;
            $this->notice = (string) __('gcego-widgets::messages.state_reset');

            return $widgets->state($definition);
        }
    }

    public function placeholder(): HtmlString
    {
        return new HtmlString('<div class="h-32 animate-pulse rounded-gcego-lg bg-gcego-surface-muted" role="status"><span class="sr-only">'.e((string) __('gcego-widgets::messages.loading')).'</span></div>');
    }
}
