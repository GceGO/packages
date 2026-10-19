<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

use Gcego\Components\Validation\ValidationError;
use Stringable;

/**
 * One structured blueprint validation failure.
 *
 * Wraps component schema errors (code "component.<code>", original in
 * $componentError) and blueprint composition errors ("blueprint.<code>")
 * in one shape.
 */
final readonly class BlueprintValidationError implements Stringable
{
    public function __construct(
        public string $code,
        public string $message,
        public string $blueprint,
        public string $path,
        public ?string $role = null,
        public ?string $expected = null,
        public ?string $received = null,
        public ?string $hint = null,
        public ?ValidationError $componentError = null,
    ) {}

    public static function fromComponentError(string $blueprint, ValidationError $error): self
    {
        return new self(
            'component.'.$error->code->value,
            $error->message,
            $blueprint,
            $error->path,
            expected: $error->expected,
            received: $error->received,
            hint: $error->hint,
            componentError: $error,
        );
    }

    public function isBlueprintError(): bool
    {
        return $this->componentError === null;
    }

    public function format(): string
    {
        $output = $this->message;
        $details = [];

        if ($this->role !== null) {
            $details[] = 'Role: '.$this->role;
        }

        if ($this->expected !== null) {
            $details[] = 'Expected: '.$this->expected;
        }

        if ($this->received !== null) {
            $details[] = 'Received: '.$this->received;
        }

        if ($details !== []) {
            $output .= "\n\n".implode("\n", $details);
        }

        if ($this->hint !== null) {
            $output .= "\n\n".$this->hint;
        }

        return $output."\n\nPath: ".$this->path;
    }

    /**
     * @return array{code: string, message: string, blueprint: string, path: string, role: string|null, expected: string|null, received: string|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'blueprint' => $this->blueprint,
            'path' => $this->path,
            'role' => $this->role,
            'expected' => $this->expected,
            'received' => $this->received,
            'hint' => $this->hint,
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
