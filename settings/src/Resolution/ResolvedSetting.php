<?php

declare(strict_types=1);

namespace Gcego\Settings\Resolution;

use Gcego\Settings\Scopes\SettingScope;

/**
 * The effective value of a setting at a target scope, and where it came from:
 *
 * - source null: the definition default (nothing stored anywhere it looks);
 * - source equal to target: overridden here (reset() returns to inherited);
 * - any other source: inherited from that less specific level.
 *
 * version is the stored version at the target (0: no override there), the
 * token a later write passes to detect concurrent changes. An explicitly
 * stored null is a value (stored: true), unlike a missing row.
 */
final readonly class ResolvedSetting
{
    public function __construct(
        public string $key,
        public mixed $value,
        public SettingScope $target,
        public ?SettingScope $source,
        public int $version,
        public bool $sensitive,
    ) {}

    public function isDefault(): bool
    {
        return $this->source === null;
    }

    public function isOverridden(): bool
    {
        return $this->source === $this->target;
    }

    public function isInherited(): bool
    {
        return ! $this->isOverridden();
    }

    public function hasValue(): bool
    {
        return $this->value !== null && $this->value !== '' && $this->value !== [];
    }
}
