<?php

declare(strict_types=1);

namespace Gcego\Pages\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Pages\Contracts\PageComponent;
use Gcego\Security\Contracts\Requirement;

/**
 * Static text on a page: paragraphs separated by blank lines, always
 * escaped (never HTML), translated when the text is a translation key:
 *
 *     new Content('GCEGO helps candidates prepare for the GCE.')
 *     new Section('About', new Content('pages.about.intro'))
 */
final class Content extends Component implements PageComponent
{
    public const string Text = 'text';

    /**
     * @return array<string, string> Slot => description.
     */
    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The text block.'];
    }

    public function text(): string
    {
        $text = $this->get(self::Text);

        return is_string($text) ? $text : '';
    }

    /**
     * The (translated) text split into paragraphs.
     *
     * @return list<string>
     */
    public function paragraphs(): array
    {
        $text = (string) __($this->text());

        return array_values(array_filter(array_map(trim(...), preg_split('/\R\s*\R/u', $text) ?: []), static fn (string $paragraph): bool => $paragraph !== ''));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('pages.content')
            ->withoutProperties(self::Label, self::Description, self::Id)
            ->withProperties(new PropertyDefinition(self::Text, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 10000, ruleDescription: 'a text of at most 10,000 characters'))
            ->withoutChildren()
            ->withAllowedConfigurations([Requirement::class, Tailwind::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Text];
    }
}
