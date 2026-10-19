<?php

declare(strict_types=1);

namespace Gcego\Pages\Records;

use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Exceptions\PageNotFoundException;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Security\Exceptions\AccessDeniedException;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * Binds the {record} of a record page:
 *
 *  1. the key must have the page's RecordKey shape (else 404, no query);
 *  2. the record is looked up through the page's gcego/data Source, so
 *     global, tenant and security scopes apply (out of scope: 404);
 *  3. the page's record ability is authorized on it (403, or 404 when the
 *     denial strategy conceals).
 *
 * A valid identifier never grants access on its own.
 */
final readonly class PageRecordResolver
{
    /** Attributes tried, in order, for a record's label when the page names none. */
    public const array LabelAttributes = ['name', 'title', 'label'];

    public function __construct(private PageAuthorizer $authorizer, private PagesConfig $config) {}

    public function validKey(Page $page, string $key): bool
    {
        return RecordKey::valid($page->recordKey($this->config->recordKey), $key);
    }

    /**
     * @throws PageNotFoundException|AccessDeniedException
     */
    public function resolve(string $identity, Page $page, string $key, ?string $ability = null): Model
    {
        if (! $this->validKey($page, $key)) {
            throw PageNotFoundException::make();
        }

        [$decision, $record] = $this->authorizer->record($identity, $page, $key, $ability);

        if (! $decision->allowed || $record === null) {
            $decision->authorize();

            throw PageNotFoundException::make();
        }

        return $record;
    }

    /**
     * A short, plain-text label for the record (titles, breadcrumbs): the
     * page's RecordTitle attribute, else name, title or label. Hidden model
     * attributes are never used. Null when nothing suitable is set.
     */
    public function label(Page $page, Model $record): ?string
    {
        $hidden = $record->getHidden();
        $attributes = $page->recordTitle() === null ? self::LabelAttributes : [$page->recordTitle()];

        foreach ($attributes as $attribute) {
            if (in_array($attribute, $hidden, true) || ! array_key_exists($attribute, $record->getAttributes())) {
                continue;
            }

            $value = $record->getAttribute($attribute);

            if (is_scalar($value) || $value instanceof Stringable) {
                $label = trim((string) $value);

                if ($label !== '') {
                    return mb_strimwidth($label, 0, 120, '…');
                }
            }
        }

        return null;
    }
}
