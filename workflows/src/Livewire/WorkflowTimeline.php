<?php

declare(strict_types=1);

namespace Gcego\Workflows\Livewire;

use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Livewire\Concerns\ResolvesViewer;
use Gcego\Workflows\Support\WorkflowLabels;
use Gcego\Workflows\Support\WorkflowStyles;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * <livewire:gcego-workflow-timeline :instance="$ulid" />: the append-only
 * history of an instance the user may see, newest first, paginated.
 * Comments are shown to viewers of the instance; actor identities are
 * display names only.
 */
final class WorkflowTimeline extends Component
{
    use ResolvesViewer, WithPagination;

    #[Locked]
    public string $instance = '';

    public function mount(string $instance): void
    {
        $this->instance = $instance;
    }

    #[On('gcego-workflow-updated')]
    public function refresh(): void {}

    public function render(): View
    {
        $repository = app(WorkflowRepository::class);

        try {
            $record = $repository->find($this->instance, $this->viewer());
        } catch (WorkflowException) {
            $record = null;
        }

        return view('gcego-workflows::livewire.timeline', [
            'record' => $record,
            'page' => $record === null ? null : $repository->history($record, is_int($page = $this->getPage('history')) ? $page : 1),
            'labels' => app(WorkflowLabels::class),
            'styles' => app(WorkflowStyles::class),
        ]);
    }
}
