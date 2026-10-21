<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

use Stringable;

/**
 * One structured blueprint validation failure.
 */
final readonly class BlueprintValidationError implements Stringable
{
    public function __construct(
        public string $code,
        public string $message,
        public string $blueprint,
        public ?string $key = null,
        public ?string $expected = null,
        public ?string $received = null,
        public ?string $hint = null,
    ) {}

    public static function of(BlueprintErrorCode $code, string $message, string $blueprint, ?string $key = null, ?string $expected = null, ?string $received = null, ?string $hint = null): self
    {
        return new self($code->value, $message, $blueprint, $key, $expected, $received, $hint);
    }

    public function format(): string
    {
        $details = array_filter([
            'Definition' => $this->key,
            'Expected' => $this->expected,
            'Received' => $this->received,
        ], static fn (?string $value): bool => $value !== null);
        $output = $this->message;

        if ($details !== []) {
            $output .= "\n".implode("\n", array_map(static fn (string $label, string $value): string => $label.': '.$value, array_keys($details), $details));
        }

        return $this->hint === null ? $output : $output."\n".$this->hint;
    }

    /**
     * @return array{code: string, message: string, blueprint: string, key: string|null, expected: string|null, received: string|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'blueprint' => $this->blueprint,
            'key' => $this->key,
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
