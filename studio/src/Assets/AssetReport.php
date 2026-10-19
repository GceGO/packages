<?php

declare(strict_types=1);

namespace Gcego\Studio\Assets;

/**
 * What the asset checks found (paths relative to the application).
 */
final readonly class AssetReport
{
    /**
     * @param  list<string>  $entries
     * @param  list<string>  $missingEntries  Entries absent from the manifest.
     * @param  list<string>  $missingFiles  Built files the manifest names but the disk lacks.
     * @param  list<string>  $problems
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $entries,
        public bool $manifestFound,
        public bool $devServer,
        public array $missingEntries,
        public array $missingFiles,
        public bool $tailwind4,
        public bool $stylingImported,
        public bool $duplicateAlpine,
        public array $problems,
        public array $warnings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['entries' => $this->entries, 'manifest_found' => $this->manifestFound, 'dev_server' => $this->devServer, 'missing_entries' => $this->missingEntries, 'missing_files' => $this->missingFiles, 'tailwind4' => $this->tailwind4, 'styling_imported' => $this->stylingImported, 'duplicate_alpine' => $this->duplicateAlpine, 'problems' => $this->problems, 'warnings' => $this->warnings];
    }
}
