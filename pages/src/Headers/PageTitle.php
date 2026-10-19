<?php

declare(strict_types=1);

namespace Gcego\Pages\Headers;

use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Contracts\PageTitleProvider;
use Illuminate\Support\Str;

/**
 * A page title, as plain text (it is always escaped, never HTML). In order:
 *
 *  1. the page's TitleProvider (trusted code, after authorization);
 *  2. Title, translated, with ":record" replaced by the record's label
 *     (Title "Edit :record" -> "Edit Chemistry");
 *  3. the record's label (view pages) or the name's last segment
 *     ("subjects.edit" -> "Edit Chemistry", "reports.overview" -> "Overview").
 */
final readonly class PageTitle
{
    public const int MaxLength = 200;

    public function __construct(public string $text) {}

    public static function for(PageContext $context, ?PageTitleProvider $provider = null): self
    {
        $label = $context instanceof RecordPageContext ? ($context->label ?? '#'.$context->key) : null;
        $provided = $provider?->title($context);

        if (is_string($provided) && trim($provided) !== '') {
            return self::make($provided);
        }

        $title = $context->page->get('title');

        if (is_string($title) && $title !== '') {
            return self::make((string) __($title, ['record' => $label ?? '']));
        }

        $segments = explode('.', $context->page->pageName());
        $last = (string) end($segments);

        if ($label === null) {
            return self::make(Str::headline($last));
        }

        return self::make(in_array($last, ['view', 'show'], true) || count($segments) === 1 ? $label : Str::headline($last).' '.$label);
    }

    public function __toString(): string
    {
        return $this->text;
    }

    private static function make(string $text): self
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return new self(mb_strimwidth($text, 0, self::MaxLength, '…'));
    }
}
