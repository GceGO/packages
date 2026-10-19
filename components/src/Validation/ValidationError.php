<?php

declare(strict_types=1);

namespace Gcego\Components\Validation;

use Gcego\Components\TreeNode;
use Stringable;

/**
 * One structured validation failure.
 *
 * format() renders the human-readable form:
 *
 *     Invalid property "perPage" on component "Table".
 *
 *     Expected: integer greater than zero
 *     Received: string "twenty"
 *
 *     Path: subjects.table
 */
final readonly class ValidationError implements Stringable
{
    public function __construct(
        public ErrorCode $code,
        public string $message,
        public string $path,
        public string $componentType,
        public string $componentClass,
        public ?string $subject = null,
        public ?string $expected = null,
        public ?string $received = null,
        public ?string $hint = null,
    ) {}

    public static function at(
        TreeNode $node,
        ErrorCode $code,
        string $message,
        ?string $subject = null,
        ?string $expected = null,
        ?string $received = null,
        ?string $hint = null,
        ?string $path = null,
    ): self {
        return new self(
            $code,
            $message,
            $path ?? $node->path->toString(),
            $node->component->type(),
            $node->component::class,
            $subject,
            $expected,
            $received,
            $hint,
        );
    }

    /**
     * A short component name for messages: the type, or the class basename when the type is a class name.
     */
    public static function displayName(TreeNode $node): string
    {
        $type = $node->component->type();
        $position = strrpos($type, '\\');

        return $position === false ? $type : substr($type, $position + 1);
    }

    public function format(): string
    {
        $output = $this->message;
        $details = [];

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
     * @return array{code: string, message: string, path: string, componentType: string, componentClass: string, subject: string|null, expected: string|null, received: string|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'path' => $this->path,
            'componentType' => $this->componentType,
            'componentClass' => $this->componentClass,
            'subject' => $this->subject,
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
