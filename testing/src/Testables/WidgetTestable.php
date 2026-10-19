<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Gcego\Widgets\Charts\ChartDataset;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;

/**
 * A registered gcego/widgets widget ("widget:students" or "students"):
 * authorization, then the data WidgetManager resolves for the acting user
 * and tenant (queries go through gcego/data scopes).
 */
final class WidgetTestable extends Testable
{
    /** @var array<string, mixed> */
    private array $state = [];

    private string|int|null $record = null;

    private ?WidgetDataResult $data = null;

    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string $identity)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);
        $this->data = null;

        return $this;
    }

    /**
     * Untrusted widget state: range, from, to, page.
     *
     * @param  array<string, mixed>  $state
     */
    public function state(array $state): self
    {
        $this->state = $state;
        $this->data = null;

        return $this;
    }

    public function record(string|int|null $record): self
    {
        $this->record = $record;
        $this->data = null;

        return $this;
    }

    public function widget(): Widget
    {
        return $this->manager()->resolve($this->identity);
    }

    /**
     * The data for the acting user, after authorization.
     */
    public function data(): WidgetDataResult
    {
        if ($this->data === null) {
            $manager = $this->manager();
            $widget = $this->widget();
            $manager->authorize($widget);
            $identity = $manager->identity($this->identity);
            $this->data = $manager->data($widget, $manager->context($identity, $widget, $manager->state($widget, $this->state), $this->record));
        }

        return $this->data;
    }

    public function assertAuthorized(): self
    {
        $allowed = $this->manager()->allows($this->widget());

        return $this->check($allowed, 'assertAuthorized()', 'Expected the acting user to see the widget.', ['allowed' => true], ['allowed' => false]);
    }

    public function assertForbidden(): self
    {
        $exception = $this->caught(fn () => $this->manager()->authorize($this->widget()));

        return $this->check($exception instanceof AuthorizationException, 'assertForbidden()', 'Expected the acting user to be refused the widget.', ['exception' => AuthorizationException::class], ['exception' => $this->describe($exception)], 'A widget with no requirement is denied to everyone; declare one.');
    }

    public function assertValue(mixed $value): self
    {
        $actual = $this->data()->value;

        return $this->check($actual == $value, sprintf('assertValue(%s)', $this->export($value)), 'Expected the widget value.', ['value' => $value], ['value' => $actual]);
    }

    public function assertPrevious(int|float|null $previous): self
    {
        $actual = $this->data()->previous;

        return $this->check($actual == $previous, sprintf('assertPrevious(%s)', $this->export($previous)), 'Expected the comparison value.', ['previous' => $previous], ['previous' => $actual]);
    }

    /**
     * @param  list<string>  $labels
     */
    public function assertLabels(array $labels): self
    {
        $actual = $this->data()->labels;

        return $this->check($actual === $labels, 'assertLabels('.$this->export($labels).')', 'Expected the chart labels.', ['labels' => $labels], ['labels' => $actual]);
    }

    /**
     * @param  list<int|float|null>  $data
     */
    public function assertDataset(int $index, ?string $label = null, ?array $data = null): self
    {
        $dataset = $this->data()->datasets[$index] ?? null;
        $passed = $dataset instanceof ChartDataset && ($label === null || $dataset->label === $label) && ($data === null || $dataset->data == $data);

        return $this->check($passed, sprintf('assertDataset(%d, %s)', $index, $this->export($label)), sprintf('Expected dataset #%d.', $index), ['label' => $label, 'data' => $data], ['label' => $dataset?->label, 'data' => $dataset?->data, 'datasets' => count($this->data()->datasets)]);
    }

    public function assertItemCount(int $count): self
    {
        $actual = count($this->data()->items);

        return $this->check($actual === $count, sprintf('assertItemCount(%d)', $count), sprintf('Expected %d item(s).', $count), ['items' => $count], ['items' => $actual]);
    }

    public function assertRefreshEvery(?int $seconds): self
    {
        $actual = $this->widget()->refresh()?->every;

        return $this->check($actual === $seconds, sprintf('assertRefreshEvery(%s)', $this->export($seconds)), 'Expected the polling interval.', ['every' => $seconds], ['every' => $actual]);
    }

    /**
     * Invalid or forged state (range, dates, page) is refused.
     *
     * @param  array<string, mixed>  $state
     */
    public function assertRejectsState(array $state): self
    {
        $exception = $this->caught(fn () => $this->manager()->state($this->widget(), $state));

        return $this->check($exception !== null && method_exists($exception, 'getStatusCode') && $exception->getStatusCode() === 400, 'assertRejectsState('.$this->export($state).')', 'Expected the widget state to be refused.', ['status' => 400], ['exception' => $this->describe($exception)]);
    }

    public function html(): HtmlTestable
    {
        return new HtmlTestable($this->verifier, $this->manager()->render($this->identity, $this->state, $this->record)->toHtml(), 'widget');
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->identity));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the widget inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'widget';
    }

    protected function context(): array
    {
        return ['Widget' => $this->identity, 'State' => $this->state, 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    private function manager(): WidgetManager
    {
        return $this->container->make(WidgetManager::class);
    }
}
