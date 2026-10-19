<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Actions;

use Gcego\Workflows\Integration\Actions\RunWorkflowTransition;

final class SubmitRegistration extends RunWorkflowTransition
{
    protected function workflow(): string
    {
        return 'registration';
    }

    protected function transition(): string
    {
        return 'submit';
    }
}
