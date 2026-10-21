<?php

declare(strict_types=1);

namespace Gcego\Media\Definitions;

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A generated image variant of a collection (trusted definition: sizes are
 * never taken from requests):
 *
 *     new Variant('thumb', new Property(Variant::Width, 320), new Property(Variant::Height, 320), new Property(Variant::Fit, 'cover'))
 *     Variant::make('medium', 960)                      // width 960, contain, webp
 */
final class Variant extends Component
{
    public const string Width = 'width';

    public const string Height = 'height';

    public const string Fit = 'fit';

    /** jpg, png or webp. */
    public const string Format = 'format';

    public const string Quality = 'quality';

    public const array Formats = ['jpg', 'png', 'webp'];

    public const string NamePattern = '/^[a-z][a-z0-9_-]{0,31}$/';

    public static function make(string $name, int $width, ?int $height = null, Fit $fit = Fit::Contain, string $format = 'webp', int $quality = 80): self
    {
        $properties = [new Property(self::Name, $name), new Property(self::Width, $width)];

        if ($height !== null) {
            $properties[] = new Property(self::Height, $height);
        }

        return new self(
            ...$properties,
            ...[new Property(self::Fit, $height === null ? Fit::Width->value : $fit->value), new Property(self::Format, $format), new Property(self::Quality, $quality)],
        );
    }

    /**
     * @param  array<array-key, mixed>  $settings  ['width' => 320, 'height' => 320, 'fit' => 'cover', 'format' => 'webp', 'quality' => 80]
     */
    public static function fromArray(string $name, array $settings): self
    {
        $properties = [new Property(self::Name, $name)];

        foreach ([self::Width, self::Height, self::Fit, self::Format, self::Quality] as $key) {
            if (array_key_exists($key, $settings)) {
                $properties[] = new Property($key, $settings[$key]);
            }
        }

        return (new self(...$properties))->assertValid();
    }

    public function variantName(): string
    {
        return is_string($name = $this->get(self::Name)) ? $name : '';
    }

    public function width(): int
    {
        return is_int($width = $this->get(self::Width)) ? $width : 0;
    }

    public function height(): ?int
    {
        return is_int($height = $this->get(self::Height)) ? $height : null;
    }

    public function fit(): Fit
    {
        return Fit::tryFrom(is_string($fit = $this->get(self::Fit)) ? $fit : '') ?? ($this->height() === null ? Fit::Width : Fit::Contain);
    }

    public function format(): string
    {
        return is_string($format = $this->get(self::Format)) ? $format : 'webp';
    }

    public function quality(): int
    {
        return is_int($quality = $this->get(self::Quality)) ? $quality : 80;
    }

    /**
     * Changes when the definition changes (variants are regenerated then).
     */
    public function signature(): string
    {
        return substr(hash('sha256', implode('|', [$this->width(), $this->height() ?? '', $this->fit()->value, $this->format(), $this->quality()])), 0, 12);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $pixels = static fn (mixed $value): bool => is_int($value) && $value >= 16 && $value <= 4096;

        return parent::defineSchema()
            ->withType('media.variant')
            ->withoutProperties(self::Label, self::Description, self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase name such as "thumb"'),
                new PropertyDefinition(self::Width, Type::int(), required: true, rule: $pixels, ruleDescription: 'between 16 and 4096 pixels'),
                new PropertyDefinition(self::Height, Type::int(), rule: $pixels, ruleDescription: 'between 16 and 4096 pixels'),
                new PropertyDefinition(self::Fit, Type::string(), allowedValues: Fit::values()),
                new PropertyDefinition(self::Format, Type::string(), default: 'webp', allowedValues: self::Formats),
                new PropertyDefinition(self::Quality, Type::int(), default: 80, rule: static fn (mixed $value): bool => is_int($value) && $value >= 30 && $value <= 100, ruleDescription: 'between 30 and 100'),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
