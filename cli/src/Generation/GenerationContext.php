<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\Exceptions\GenerationException;

/**
 * What a generator is asked to produce: the name and the options, already
 * collected (from arguments, options or prompts) by the command.
 */
final readonly class GenerationContext
{
    /**
     * @param  array<string, string|bool|list<string>|null>  $options
     */
    public function __construct(public string $name, public array $options = [], public bool $force = false) {}

    public function string(string $option): ?string
    {
        $value = $this->options[$option] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function required(string $option, string $hint): string
    {
        return $this->string($option) ?? throw GenerationException::invalid(sprintf('The --%s option is required: %s', $option, $hint));
    }

    public function flag(string $option): bool
    {
        return ($this->options[$option] ?? false) === true;
    }

    /**
     * @return list<string>
     */
    public function list(string $option): array
    {
        $value = $this->options[$option] ?? [];
        $values = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);

        return array_values(array_filter(array_map('trim', $values), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @return array<string, string|bool|list<string>|null>
     */
    public function toArray(): array
    {
        return ['name' => $this->name, ...$this->options, 'force' => $this->force];
    }
}
