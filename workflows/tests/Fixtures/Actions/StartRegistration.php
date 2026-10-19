<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Actions;

use Gcego\Workflows\Integration\Actions\StartWorkflow;

final class StartRegistration extends StartWorkflow
{
    protected function workflow(): string
    {
        return 'registration';
    }
}
