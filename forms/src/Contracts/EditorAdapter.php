<?php

declare(strict_types=1);

namespace Gcego\Forms\Contracts;

/**
 * How a RichEditor is presented. The default TextareaEditor is a plain
 * textarea (no JavaScript editor is bundled); a gcego/media or application
 * package may provide a WYSIWYG adapter. Whatever the adapter, the server
 * sanitizes the HTML before it is stored.
 */
interface EditorAdapter
{
    /**
     * The Blade view rendering the control. It receives the field view data plus $editor.
     */
    public function view(): string;
}
