<?php

declare(strict_types=1);

namespace Gcego\Workflows\Approvals;

/**
 * When a stage is approved:
 *
 * - any: one approval;
 * - all: every assigned reviewer (pool stages: RequiredApprovals);
 * - quorum: RequiredApprovals approvals;
 * - majority: more than half of the assigned reviewers.
 *
 * A rejection rejects the approval; a request for changes sends it back.
 */
enum ApprovalPolicy: string
{
    case Any = 'any';
    case All = 'all';
    case Quorum = 'quorum';
    case Majority = 'majority';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $policy): string => $policy->value, self::cases());
    }

    /**
     * Approvals needed, given the number of reviewers (null: a pool).
     */
    public function needed(int $required, ?int $reviewers): int
    {
        return match ($this) {
            self::Any => 1,
            self::All => $reviewers ?? $required,
            self::Quorum => $required,
            self::Majority => intdiv($reviewers ?? $required, 2) + 1,
        };
    }
}
