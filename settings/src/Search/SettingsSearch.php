<?php

declare(strict_types=1);

namespace Gcego\Settings\Search;

use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Pages\SettingsPageResolver;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Searches the settings a viewer may see in a panel, by key, label,
 * description, group, section and keywords. It searches definitions only:
 * stored values (and secrets) are never searched or returned.
 */
final readonly class SettingsSearch
{
    public const int Limit = 25;

    public function __construct(private SettingsPageResolver $pages) {}

    /**
     * @return list<array{page: string, page_title: string, key: string, label: string, section: string|null}>
     */
    public function search(string $query, ?Authenticatable $user, ?string $panel): array
    {
        $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim(mb_substr($query, 0, 100)))) ?: [], static fn (string $term): bool => $term !== ''));

        if ($terms === []) {
            return [];
        }

        $results = [];

        foreach ($this->pages->screens($user, $panel) as $screen) {
            $sections = [];

            foreach ($screen->group->sections() as $section) {
                $sections[$section->nodeName()] = $section;
            }

            foreach ($screen->visible as $definition) {
                $section = $definition->section === null ? null : ($sections[$definition->section] ?? null);
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $definition->key,
                    str_replace('_', ' ', $definition->key),
                    $definition->label(),
                    $definition->description(),
                    (string) __($screen->group->title()),
                    $section instanceof SettingsSection ? (string) __($section->label()) : null,
                    ...array_map(static fn (string $keyword): string => (string) __($keyword), $definition->setting->keywords()),
                ])));

                foreach ($terms as $term) {
                    if (! str_contains($haystack, $term)) {
                        continue 2;
                    }
                }

                $results[] = ['page' => $screen->page->pageName(), 'page_title' => (string) __($screen->page->title()), 'key' => $definition->key, 'label' => $definition->label(), 'section' => $section instanceof SettingsSection ? (string) __($section->label()) : null];

                if (count($results) >= self::Limit) {
                    return $results;
                }
            }
        }

        return $results;
    }
}
