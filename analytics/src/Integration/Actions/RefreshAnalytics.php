<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Actions;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Analytics\Cache\AnalyticsCache;

/**
 * A gcego/actions handler invalidating cached analytics of a dataset
 * (for everyone), e.g. after an import. Protect the action with an ability:
 *
 *     final class RefreshAssessmentAnalytics extends RefreshAnalytics
 *     {
 *         protected function dataset(): ?string { return 'assessment-results'; }
 *     }
 */
abstract class RefreshAnalytics implements ActionHandler
{
    abstract protected function dataset(): ?string;

    public function handle(ActionContext $context): bool
    {
        app(AnalyticsCache::class)->flush($this->dataset());

        return true;
    }
}
