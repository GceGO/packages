<?php

declare(strict_types=1);

namespace Gcego\Widgets\Activity;

use BackedEnum;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Luminaries\IconName;
use Gcego\Widgets\Exceptions\InvalidWidgetException;
use Illuminate\Database\Eloquent\Model;

/**
 * An activity feed read from records (an audit or activity table, or any
 * model), newest first, through gcego/data as the current user:
 *
 *     new ActivitySource(ActivityLog::class, title: 'description', time: 'created_at', actor: 'causer.name')
 *     new ActivitySource('blueprint:payments', title: 'reference', time: 'paid_at', description: 'student.name',
 *         route: 'payments.show', parameters: ['payment' => 'id'])
 *
 * Paths are trusted, definition-time paths into each record; relations they
 * read are eager-loaded (one query each) under the related model's scopes.
 * Only these fields are shown, so private columns of the records never are.
 */
final readonly class ActivitySource implements ConfigurationNode
{
    public const string PathPattern = '/^[a-z_][A-Za-z0-9_]{0,63}(?:\.[a-z_][A-Za-z0-9_]{0,63}){0,3}$/';

    public ?string $icon;

    /**
     * @param  class-string<Model>|string  $source  A model class or a blueprint identity.
     * @param  array<string, string>  $parameters  route parameter => record path
     */
    public function __construct(
        public string $source,
        public string $title,
        public string $time,
        public ?string $description = null,
        public ?string $actor = null,
        string|BackedEnum|null $icon = null,
        public ?string $route = null,
        public array $parameters = [],
        public ?string $badge = null,
    ) {
        if (! str_starts_with($source, 'blueprint:') && ! is_subclass_of($source, Model::class)) {
            throw InvalidWidgetException::invalid('ActivitySource', 'the source must be an Eloquent model class or a blueprint identity.');
        }

        foreach ([$title, $time, $description, $actor, $badge, ...array_values($parameters)] as $path) {
            if ($path !== null && (! is_string($path) || preg_match(self::PathPattern, $path) !== 1)) {
                throw InvalidWidgetException::invalid('ActivitySource', sprintf('"%s" is not a record path.', mb_substr((string) $path, 0, 64)));
            }
        }

        if (str_contains($time, '.')) {
            throw InvalidWidgetException::invalid('ActivitySource', 'time must be a column of the source itself (it orders the feed).');
        }

        $this->icon = $icon === null ? null : IconName::tryOf($icon) ?? throw InvalidWidgetException::invalid('ActivitySource', 'icon must be an icon name.');
    }

    /**
     * Relation paths the shown fields read.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $relations = [];

        foreach ([$this->title, $this->description, $this->actor, $this->badge, ...array_values($this->parameters)] as $path) {
            if ($path !== null && str_contains($path, '.')) {
                $relations[] = substr($path, 0, (int) strrpos($path, '.'));
            }
        }

        return array_values(array_unique($relations));
    }

    /**
     * @return array<string, scalar|null>
     */
    public function normalize(): array
    {
        return ['source' => $this->source, 'title' => $this->title, 'time' => $this->time, 'description' => $this->description, 'actor' => $this->actor, 'route' => $this->route];
    }
}
