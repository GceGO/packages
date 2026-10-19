<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Data\Source;

/**
 * What the relationship machinery needs to know about one relation: which
 * parent records (the parent resource's Source, so its tenant and security
 * scopes apply), which relationship method, and which pages carry the
 * {parent} parameter. Trusted data from the definition, never from requests.
 */
final readonly class RelationReference
{
    /**
     * @param  class-string|null  $model  the related model
     * @param  list<string>  $pages  registry names of the relation's pages ("academics-subjects-topics-create")
     */
    public function __construct(
        public string $module,
        public string $resource,
        public string $relationship,
        public Source $parentSource,
        public ?string $model,
        public array $pages,
    ) {}

    public function identity(): string
    {
        return $this->module.'.'.$this->resource.'.'.$this->relationship;
    }

    /**
     * Whether a page identity ("page:academics-subjects-topics", "page:admin.academics-subjects-topics") is one of the relation's pages.
     */
    public function ownsPage(string $identity): bool
    {
        $name = str_starts_with($identity, 'page:') ? substr($identity, 5) : $identity;
        $dot = strrpos($name, '.');

        return in_array($dot === false ? $name : substr($name, $dot + 1), $this->pages, true);
    }
}
