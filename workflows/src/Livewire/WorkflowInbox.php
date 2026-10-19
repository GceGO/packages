<?php

declare(strict_types=1);

namespace Gcego\Workflows\Livewire;

use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Livewire\Concerns\ResolvesViewer;
use Gcego\Workflows\Support\WorkflowLabels;
use Gcego\Workflows\Support\WorkflowStyles;
use Gcego\Workflows\Tasks\Inbox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * <livewire:gcego-workflow-inbox />: the signed-in user's open approvals
 * and tasks in the current tenant (assigned, and pool work they are
 * eligible for), filtered and paginated on the server. Pool tasks can be
 * claimed; decisions are made on the instance page.
 */
final class WorkflowInbox extends Component
{
    use ResolvesViewer, WithPagination;

    public const array Filters = ['all', 'approvals', 'tasks', 'overdue'];

    #[Url(except: 'all')]
    public string $filter = 'all';

    public ?string $message = null;

    /** Link pattern to an instance page; ":instance" is replaced (set by the panel extension). */
    #[Locked]
    public ?string $link = null;

    public function updatedFilter(): void
    {
        if (! in_array($this->filter, self::Filters, true)) {
            $this->filter = 'all';
        }

        $this->resetPage();
    }

    public function claim(mixed $task): void
    {
        $this->message = null;
        $this->resetErrorBag();

        if (! is_string($task)) {
            return;
        }

        try {
            $model = app(WorkflowRepository::class)->findTask($task, $this->viewer());
            app(WorkflowEngine::class)->claim($model, $this->viewer());
            $this->message = (string) __('gcego-workflows::messages.claimed');
        } catch (WorkflowException $exception) {
            $this->addError('inbox', $this->refusal($exception));
        }
    }

    #[On('gcego-workflow-updated')]
    public function refresh(): void {}

    public function render(): View
    {
        $filter = in_array($this->filter, self::Filters, true) ? $this->filter : 'all';

        return view('gcego-workflows::livewire.inbox', [
            'page' => app(Inbox::class)->for($this->viewer(), is_int($page = $this->getPage()) ? $page : 1, $filter === 'all' ? null : $filter),
            'labels' => app(WorkflowLabels::class),
            'styles' => app(WorkflowStyles::class),
            'filters' => self::Filters,
        ]);
    }
}
