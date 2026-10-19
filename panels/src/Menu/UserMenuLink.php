<?php

declare(strict_types=1);

namespace Gcego\Panels\Menu;

/**
 * A resolved user-menu entry: a link, or (for signing out) a POST form.
 */
final readonly class UserMenuLink
{
    public function __construct(
        public string $label,
        public string $url,
        public ?string $icon = null,
        public bool $post = false,
    ) {}
}
