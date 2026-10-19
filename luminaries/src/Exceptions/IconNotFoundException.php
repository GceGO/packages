<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Exceptions;

/**
 * An icon could not be resolved. The message carries the diagnostics a
 * developer needs: what was requested, through which provider or alias, and
 * (when derived from registered metadata) similarly named icons.
 */
final class IconNotFoundException extends LuminariesException
{
    /**
     * @param  list<string>  $aliasChain
     * @param  list<string>  $suggestions
     * @param  array{0: string, 1: string, 2?: string}|null  $hint  Where similar names can be looked up later: ['aliases', name], ['providers', name] or ['icons', provider, icon].
     */
    public function __construct(
        public readonly string $requested,
        public readonly string $provider,
        public readonly ?string $mapping,
        public readonly string $issue,
        public readonly array $aliasChain = [],
        public readonly array $suggestions = [],
        public readonly ?array $hint = null,
    ) {
        $lines = [sprintf('Icon not found: %s', $requested), '', sprintf('Provider: %s', $provider)];

        if ($mapping !== null) {
            $lines[] = sprintf('Configured mapping: %s', $mapping);
        }

        if (count($aliasChain) > 1) {
            $lines[] = sprintf('Alias chain: %s', implode(' -> ', $aliasChain));
        }

        $lines[] = '';
        $lines[] = 'Possible issue:';
        $lines[] = $issue;

        if ($suggestions !== []) {
            $lines[] = '';
            $lines[] = 'Registered icons with similar names: '.implode(', ', $suggestions);
        }

        parent::__construct(self::header().implode("\n", $lines));
    }

    /**
     * The same failure with suggestions in its message (computed only when the exception is about to be shown).
     *
     * @param  list<string>  $suggestions
     */
    public function withSuggestions(array $suggestions): self
    {
        return new self($this->requested, $this->provider, $this->mapping, $this->issue, $this->aliasChain, $suggestions);
    }
}
