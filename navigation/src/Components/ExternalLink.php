<?php

declare(strict_types=1);

namespace Gcego\Navigation\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Navigation\NavigationNode;
use Gcego\Tables\Support\SafeUrl;

/**
 * A link outside the application:
 *
 *     new ExternalLink('Documentation', 'https://docs.example.com')
 *     new ExternalLink('Support', 'mailto:support@example.com', new Property(ExternalLink::NewTab, false))
 *
 * Only http(s) and mailto URLs are accepted (never javascript: or data:).
 * It opens in a new tab by default, with rel="noopener noreferrer" and a
 * screen-reader note, and shows an external-link icon.
 */
final class ExternalLink extends NavigationNode
{
    public const string Url = 'url';

    public const string NewTab = 'new_tab';

    public function nodeType(): string
    {
        return 'external';
    }

    public function url(): ?string
    {
        $url = $this->string(self::Url);

        return $url === null || str_starts_with($url, '/') ? null : SafeUrl::link($url);
    }

    public function opensInNewTab(): bool
    {
        return $this->get(self::NewTab) !== false;
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Url];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true),
                new PropertyDefinition(self::Url, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && ! str_starts_with($value, '/') && SafeUrl::link($value) !== null, ruleDescription: 'an http(s) or mailto URL'),
                new PropertyDefinition(self::NewTab, Type::bool(), default: true),
                self::iconProperty(),
            )
            ->withoutChildren();
    }
}
