<?php

declare(strict_types=1);

namespace Gcego\Pages\Lifecycle;

/**
 * The stages of a page request, in order. Hooks are told about each stage
 * after it completed. Interaction marks a Livewire update of an open page
 * (the stages before it ran again: every update re-authorizes).
 */
enum PageStage: string
{
    case Resolved = 'resolved';
    case ContextCreated = 'context_created';
    case Authorized = 'authorized';
    case RecordResolved = 'record_resolved';
    case Prepared = 'prepared';
    case Interaction = 'interaction';
    case Rendering = 'rendering';
    case Rendered = 'rendered';
    case Completed = 'completed';
}
