<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Illuminate\Contracts\Config\Repository;

/**
 * A class-based handler with an injected dependency.
 */
final readonly class PublishSubject implements ActionHandler
{
    public function __construct(private Repository $config) {}

    public function handle(ActionContext $context): Subject
    {
        /** @var Subject $subject */
        $subject = $context->record;
        $subject->update(['is_published' => true]);
        $this->config->set('fixtures.published', $subject->id);

        return $subject;
    }
}
