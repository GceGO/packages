<?php

declare(strict_types=1);

namespace Gcego\Cli\Stubs;

use Gcego\Cli\Exceptions\StubException;
use ParseError;
use PhpToken;

/**
 * Renders a stub: every {{ placeholder }} must have a value (an unknown one
 * in a customized stub is reported, never rendered as-is); a customized
 * stub may leave values out. Values are typed StubValues; the result must
 * parse as PHP.
 */
final readonly class StubRenderer
{
    public function __construct(private StubManager $stubs) {}

    /**
     * @param  array<string, StubValue>  $values
     */
    public function render(string $stub, array $values): string
    {
        $source = $this->stubs->contents($stub);
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $source, $matches);
        $placeholders = array_values(array_unique($matches[1]));

        if (preg_match('/\{\{(?!\s*[a-z_]+\s*\}\})/', $source) === 1) {
            throw StubException::because($stub, 'has a malformed placeholder (use {{ name }} with lowercase letters and _).');
        }

        foreach ($placeholders as $placeholder) {
            if (! isset($values[$placeholder])) {
                throw StubException::because($stub, sprintf('uses the unknown placeholder {{ %s }} (known: %s).', $placeholder, implode(', ', array_keys($values)) ?: 'none'));
            }
        }

        $rendered = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn (array $match): string => $values[$match[1]]->rendered, $source);

        return $this->assertParses($stub, $rendered);
    }

    /**
     * @return list<string>
     */
    public function placeholders(string $stub): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $this->stubs->contents($stub), $matches);

        return array_values(array_unique($matches[1]));
    }

    public function assertParses(string $stub, string $php): string
    {
        if (str_ends_with($stub, '.json') || ! str_starts_with(ltrim($php), '<?php')) {
            return $php;
        }

        try {
            PhpToken::tokenize($php, TOKEN_PARSE);
        } catch (ParseError $error) {
            throw StubException::because($stub, sprintf('renders invalid PHP (line %d: %s).', $error->getLine(), $error->getMessage()));
        }

        return $php;
    }
}
