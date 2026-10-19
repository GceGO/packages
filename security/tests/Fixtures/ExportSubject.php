<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Operations\SecuredOperation;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

/**
 * A queued export as a future actions/exports package would write it:
 * the payload is a SecuredOperation, re-authorized when the job runs.
 */
final class ExportSubject implements ShouldQueue
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $operation
     */
    public function __construct(public array $operation) {}

    public function handle(SecurityManager $security): void
    {
        $operation = SecuredOperation::fromArray($this->operation);

        $security->runOperation($operation, static function (AuthorizationContext $context, mixed $subject) use ($operation): void {
            if ($subject instanceof Subject) {
                Storage::disk('local')->put($operation->storagePath('subject.txt'), $subject->name);
            }
        });
    }
}
