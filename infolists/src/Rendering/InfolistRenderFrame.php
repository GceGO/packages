<?php

declare(strict_types=1);

namespace Gcego\Infolists\Rendering;

use Gcego\Infolists\InfolistsConfig;
use Gcego\Infolists\Resolution\EntryAccess;
use Gcego\Infolists\Schema\InfolistSchema;

/**
 * What one render of an infolist needs: the schema, the current record (the
 * authorized record, or a repeatable item inside it), entry access and the
 * scope of repeatable items.
 */
final readonly class InfolistRenderFrame
{
    public function __construct(
        public InfolistSchema $schema,
        public mixed $record,
        public EntryAccess $access,
        public InfolistsConfig $config,
        public string $hostId,
        public string $definition,
        public bool $live = false,
        public ?string $scope = null,
        public string $idSuffix = '',
        public mixed $root = null,
    ) {}

    /**
     * The frame for one item of a repeatable entry.
     */
    public function withItem(string $scope, mixed $item, string $key): self
    {
        return new self($this->schema, $item, $this->access, $this->config, $this->hostId, $this->definition, $this->live, $scope, $this->idSuffix.'-'.preg_replace('/[^A-Za-z0-9_-]/', '', $key), $this->root ?? $this->record);
    }

    public function path(string $name): string
    {
        return ($this->scope === null ? '' : $this->scope.'.').$name;
    }

    public function id(string $suffix): string
    {
        return $this->hostId.'-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $suffix.$this->idSuffix), '-');
    }

    public function readable(string $path): bool
    {
        return $this->access->canReadPath($this->path($path));
    }
}
