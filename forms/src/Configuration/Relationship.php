<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\State\StatePath;

/**
 * Options from an Eloquent relationship of the form's model:
 *
 *     new Select('category_id', 'Category', new Relationship('category'))              // BelongsTo
 *     new Select('tags', 'Tags', new Property(Select::Multiple, true), new Relationship('tags', 'label'))   // BelongsToMany
 *     new Relationship('category', query: fn (Builder $query) => $query->where('active', true))
 *
 * Options are read through gcego/data as the current user: the related
 * model's policy (viewAny), tenant and security scopes all apply, at most
 * gcego-forms.options.limit rows. Submitted ids are re-checked with the same
 * scoped query, so ids outside it are rejected.
 */
final readonly class Relationship implements ConfigurationNode
{
    /**
     * @param  Closure|null  $query  Trusted constraint on the related query.
     */
    public function __construct(
        public string $name,
        public string $title = 'name',
        public ?Closure $query = null,
    ) {
        StatePath::assertName($name);

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $title) !== 1) {
            throw InvalidFieldException::invalid('Relationship "'.$name.'"', 'the title attribute must be a column name.');
        }
    }

    /**
     * @return array{relationship: string, title: string}
     */
    public function normalize(): array
    {
        return ['relationship' => $this->name, 'title' => $this->title];
    }
}
