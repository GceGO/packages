<?php

declare(strict_types=1);

namespace Gcego\Pages\Contracts;

use Gcego\Pages\Context\PageContext;

/**
 * Trusted dynamic page titles and descriptions (Page::TitleProvider):
 *
 *     final class SubjectTitle implements PageTitleProvider
 *     {
 *         public function title(PageContext $context): ?string
 *         {
 *             return $context instanceof RecordPageContext ? 'Subject '.$context->record->getAttribute('code') : null;
 *         }
 *
 *         public function description(PageContext $context): ?string
 *         {
 *             return null;
 *         }
 *     }
 *
 * Called after the page and its record are authorized. Return plain text
 * (it is always escaped) or null to fall back to Title / Description.
 */
interface PageTitleProvider
{
    public function title(PageContext $context): ?string;

    public function description(PageContext $context): ?string;
}
