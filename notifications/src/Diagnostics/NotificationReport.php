<?php

declare(strict_types=1);

namespace Gcego\Notifications\Diagnostics;

/**
 * The notification setup: enabled channels, infrastructure and problems.
 * Contains no notification content, recipients or credentials.
 */
final readonly class NotificationReport
{
    /**
     * @param  array<string, bool>  $channels  Channel => enabled.
     * @param  array<string, string|int|bool|null>  $infrastructure
     * @param  list<string>  $categories
     * @param  list<NotificationDiagnostic>  $diagnostics
     */
    public function __construct(
        public array $channels,
        public array $infrastructure,
        public array $categories,
        public array $diagnostics,
    ) {}

    public function healthy(): bool
    {
        return array_filter($this->diagnostics, static fn (NotificationDiagnostic $diagnostic): bool => $diagnostic->level === 'error') === [];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn (NotificationDiagnostic $diagnostic): string => $diagnostic->code, $this->diagnostics);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channels' => $this->channels,
            'infrastructure' => $this->infrastructure,
            'categories' => $this->categories,
            'healthy' => $this->healthy(),
            'diagnostics' => array_map(static fn (NotificationDiagnostic $diagnostic): array => ['level' => $diagnostic->level, 'code' => $diagnostic->code, 'message' => $diagnostic->message], $this->diagnostics),
        ];
    }
}
