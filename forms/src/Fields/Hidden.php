<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

/**
 * A value carried by the form but never taken from the browser: the submitted
 * value is always ignored and the server value (record or Default) is kept.
 * Nothing is rendered to the browser; to persist a server-chosen value, keep
 * it Dehydrated (the default).
 */
class Hidden extends Field
{
    public function view(): string
    {
        return 'gcego-forms::fields.hidden';
    }

    public function acceptsSubmission(): bool
    {
        return false;
    }
}
