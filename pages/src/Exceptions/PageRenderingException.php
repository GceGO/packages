<?php

declare(strict_types=1);

namespace Gcego\Pages\Exceptions;

use RuntimeException;

/**
 * A page cannot be rendered in the way requested (for example a Page
 * rendered without its registry identity, so its parts could not be trusted).
 */
final class PageRenderingException extends RuntimeException implements PageException
{
    public static function unregistered(string $page): self
    {
        return new self(sprintf('Page "%s" renders through its registry identity (PageManager::render("page:…") or a page route), so its tables, forms and actions can be trusted.', $page));
    }
}
