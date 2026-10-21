<?php

declare(strict_types=1);

namespace Gcego\Panels\Branding;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Panels\Support\SafeUrl;

/**
 * How a panel presents itself:
 *
 *     new Branding(
 *         new Property(Branding::Name, 'GCEGO School Portal'),
 *         new Property(Branding::Logo, '/images/gcego-logo.svg'),
 *         new Property(Branding::LogoDark, '/images/gcego-logo-dark.svg'),
 *     )
 *
 * Trusted configuration: URLs must be root-relative or https, text is
 * always escaped, and no value is ever rendered as HTML.
 */
final class Branding extends Component
{
    public const string Logo = 'logo';

    public const string LogoDark = 'logo_dark';

    public const string CompactLogo = 'compact_logo';

    public const string Favicon = 'favicon';

    public const string Tagline = 'tagline';

    public const string HomeUrl = 'home_url';

    public function brandName(): ?string
    {
        return $this->text(self::Name);
    }

    public function logo(): ?string
    {
        return $this->text(self::Logo);
    }

    public function logoDark(): ?string
    {
        return $this->text(self::LogoDark);
    }

    public function compactLogo(): ?string
    {
        return $this->text(self::CompactLogo);
    }

    public function favicon(): ?string
    {
        return $this->text(self::Favicon);
    }

    public function tagline(): ?string
    {
        return $this->text(self::Tagline);
    }

    public function homeUrl(): ?string
    {
        return $this->text(self::HomeUrl);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $text = static fn (int $max): \Closure => static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;
        $url = static fn (mixed $value): bool => SafeUrl::asset($value);
        $description = 'a root-relative path ("/images/logo.svg") or an https URL';

        return parent::defineSchema()
            ->withType('panels.branding')
            ->withoutProperties(self::Label, self::Description, self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: $text(120), ruleDescription: 'a name of at most 120 characters'),
                new PropertyDefinition(self::Logo, Type::string(), rule: $url, ruleDescription: $description),
                new PropertyDefinition(self::LogoDark, Type::string(), rule: $url, ruleDescription: $description),
                new PropertyDefinition(self::CompactLogo, Type::string(), rule: $url, ruleDescription: $description),
                new PropertyDefinition(self::Favicon, Type::string(), rule: $url, ruleDescription: $description),
                new PropertyDefinition(self::Tagline, Type::string(), rule: $text(200), ruleDescription: 'a tagline of at most 200 characters'),
                new PropertyDefinition(self::HomeUrl, Type::string(), rule: $url, ruleDescription: $description),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    private function text(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
