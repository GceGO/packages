<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Titles;

use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Contracts\PageTitleProvider;

final class SubjectTitle implements PageTitleProvider
{
    public function title(PageContext $context): ?string
    {
        $code = $context instanceof RecordPageContext ? $context->record->getAttribute('code') : null;

        return is_string($code) ? 'Subject '.$code : null;
    }

    public function description(PageContext $context): ?string
    {
        return $context instanceof RecordPageContext ? 'Code '.$context->key.' of <b>the</b> catalogue' : null;
    }
}
