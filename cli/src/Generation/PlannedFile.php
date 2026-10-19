<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

/**
 * One file a generator produces: where, what, and what writing it would do.
 */
final readonly class PlannedFile
{
    public function __construct(
        public string $path,
        public string $relativePath,
        public string $contents,
        public string $description,
        public FileOperation $operation = FileOperation::Create,
        public ?string $namespace = null,
        public ?string $class = null,
    ) {}

    public function withOperation(FileOperation $operation): self
    {
        return new self($this->path, $this->relativePath, $this->contents, $this->description, $operation, $this->namespace, $this->class);
    }

    /**
     * @return array{path: string, description: string, operation: string, namespace: string|null, class: string|null, bytes: int}
     */
    public function toArray(): array
    {
        return ['path' => $this->relativePath, 'description' => $this->description, 'operation' => $this->operation->value, 'namespace' => $this->namespace, 'class' => $this->class, 'bytes' => strlen($this->contents)];
    }
}
