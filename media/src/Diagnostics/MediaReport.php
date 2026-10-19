<?php

declare(strict_types=1);

namespace Gcego\Media\Diagnostics;

/**
 * The media setup and its problems. No file content, paths of files or credentials.
 */
final readonly class MediaReport
{
    /**
     * @param  array<string, array{driver: string|null, available: bool, public_url: bool}>  $disks
     * @param  array<string, string|int|bool|null>  $settings
     * @param  array<string, int>  $statuses
     * @param  list<string>  $collections
     * @param  list<MediaDiagnostic>  $diagnostics
     */
    public function __construct(
        public array $disks,
        public array $settings,
        public array $statuses,
        public array $collections,
        public array $diagnostics,
    ) {}

    public function healthy(): bool
    {
        return array_filter($this->diagnostics, static fn (MediaDiagnostic $diagnostic): bool => $diagnostic->level === 'error') === [];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn (MediaDiagnostic $diagnostic): string => $diagnostic->code, $this->diagnostics);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'disks' => $this->disks,
            'settings' => $this->settings,
            'statuses' => $this->statuses,
            'collections' => $this->collections,
            'healthy' => $this->healthy(),
            'diagnostics' => array_map(static fn (MediaDiagnostic $diagnostic): array => ['level' => $diagnostic->level, 'code' => $diagnostic->code, 'message' => $diagnostic->message], $this->diagnostics),
        ];
    }
}
