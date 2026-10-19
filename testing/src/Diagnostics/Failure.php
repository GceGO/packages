<?php

declare(strict_types=1);

namespace Gcego\Testing\Diagnostics;

/**
 * What a failed assertion reports: the subject under test, the assertion
 * that failed, the expected and actual values and an optional suggestion.
 */
final readonly class Failure
{
    /**
     * @param  array<string, mixed>  $context  Identifies the subject (e.g. "Table" => "subjects").
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    public function __construct(
        public string $subject,
        public string $assertion,
        public string $message,
        public array $context = [],
        public array $expected = [],
        public array $actual = [],
        public ?string $suggestion = null,
    ) {}
}
