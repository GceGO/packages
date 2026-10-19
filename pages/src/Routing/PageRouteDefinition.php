<?php

declare(strict_types=1);

namespace Gcego\Pages\Routing;

use Gcego\Pages\Exceptions\InvalidPageException;
use Gcego\Pages\Page;
use Gcego\Registry\Index\DefinitionDescriptor;

/**
 * The route of one page, as plain data: built from a page definition when it
 * is registered, stored in the registry index (scalar metadata), and read
 * back from there when routes are registered, so registering routes never
 * loads page definitions.
 */
final readonly class PageRouteDefinition
{
    /**
     * @param  list<string>  $middleware
     * @param  array<string, string>  $where
     */
    public function __construct(
        public string $page,
        public string $path,
        public string $route,
        public array $middleware = [],
        public array $where = [],
        public bool $record = false,
        public ?string $recordKey = null,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->pageName(),
            $page->path(),
            $page->routeName(),
            $page->middleware(),
            $page->where(),
            $page->requiresRecord(),
            $page->get(Page::RecordKey) === null ? null : $page->recordKey(),
        );
    }

    /**
     * @throws InvalidPageException when the descriptor is not a page's.
     */
    public static function fromDescriptor(DefinitionDescriptor $descriptor): self
    {
        $metadata = $descriptor->metadata;
        $string = static fn (string $key): string => is_string($metadata[$key] ?? null) ? $metadata[$key] : throw InvalidPageException::invalid('Page "'.$descriptor->key().'"', sprintf('its indexed "%s" is missing; rebuild the registry cache (php artisan gcego:registry-cache).', $key));
        $middleware = json_decode($string('middleware'), true);
        $where = json_decode($string('where'), true);
        $recordKey = $metadata['record_key'] ?? null;

        if (! is_array($middleware) || ! is_array($where)) {
            throw InvalidPageException::invalid('Page "'.$descriptor->key().'"', 'its indexed route metadata is corrupt; rebuild the registry cache.');
        }

        $constraints = [];

        foreach ($where as $parameter => $pattern) {
            if (is_string($parameter) && is_string($pattern)) {
                $constraints[$parameter] = $pattern;
            }
        }

        return new self(
            $string('name'),
            $string('path'),
            $string('route'),
            array_values(array_filter($middleware, is_string(...))),
            $constraints,
            ($metadata['record'] ?? false) === true,
            is_string($recordKey) ? $recordKey : null,
        );
    }

    /**
     * @return array{path: string, route: string, middleware: string, where: string, record: bool, record_key: string|null}
     */
    public function toMetadata(): array
    {
        return [
            'path' => $this->path,
            'route' => $this->route,
            'middleware' => (string) json_encode($this->middleware, JSON_UNESCAPED_SLASHES),
            'where' => (string) json_encode((object) $this->where, JSON_UNESCAPED_SLASHES),
            'record' => $this->record,
            'record_key' => $this->recordKey,
        ];
    }

    /**
     * The path's parameters, in order.
     *
     * @return list<string>
     */
    public function parameters(): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/', $this->path, $matches);

        return $matches[1];
    }
}
