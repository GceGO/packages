<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console\Concerns;

use Illuminate\Console\Command;

/**
 * The --format=text|json option shared by the gcego:icons:* commands.
 *
 * @mixin Command
 */
trait FormatsOutput
{
    protected function json(): bool
    {
        return $this->option('format') === 'json';
    }

    protected function invalidFormat(): ?int
    {
        if (in_array($this->option('format'), ['text', 'json'], true)) {
            return null;
        }

        $this->components->error('The --format option must be "text" or "json".');

        return self::INVALID;
    }

    /**
     * @param  array<mixed>  $data
     */
    protected function emit(array $data): void
    {
        $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
