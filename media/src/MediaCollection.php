<?php

declare(strict_types=1);

namespace Gcego\Media;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A named set of media with its rules:
 *
 *     (new MediaCollection('lesson-resources'))->disk('local')->maxFiles(20)
 *     (new MediaCollection('avatar'))->image()->single()->variants(Variant::make('thumb', 160, 160, Fit::Cover))
 *
 * Register collections while booting (MediaManager::collection()) or in
 * gcego-media.collections.definitions. Uploads to an unregistered
 * collection use the default rules.
 */
final class MediaCollection extends Media
{
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    public function collectionName(): string
    {
        return is_string($name = $this->get(self::Name)) ? $name : '';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('media.collection')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && strlen($value) <= 64 && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase name such as "lesson-resources"'));
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
