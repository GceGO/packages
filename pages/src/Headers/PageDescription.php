<?php

declare(strict_types=1);

namespace Gcego\Pages\Headers;

use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Contracts\PageTitleProvider;

/**
 * A page description, as plain text: the TitleProvider's, else Description
 * translated with ":record" replaced by the record's label. Null when the
 * page has none.
 */
final readonly class PageDescription
{
    public const int MaxLength = 500;

    public function __construct(public string $text) {}

    public static function for(PageContext $context, ?PageTitleProvider $provider = null): ?self
    {
        $provided = $provider?->description($context);

        if (is_string($provided) && trim($provided) !== '') {
            return new self(mb_strimwidth(trim($provided), 0, self::MaxLength, '…'));
        }

        $description = $context->page->pageDescription();

        if ($description === null) {
            return null;
        }

        $label = $context instanceof RecordPageContext ? ($context->label ?? '#'.$context->key) : '';
        $text = trim((string) __($description, ['record' => $label]));

        return $text === '' ? null : new self(mb_strimwidth($text, 0, self::MaxLength, '…'));
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
