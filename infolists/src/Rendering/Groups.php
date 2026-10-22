<?php

declare(strict_types=1);

namespace Gcego\Infolists\Rendering;

use Gcego\Infolists\Entries\Entry;
use Gcego\Rendering\Output\Children;

/**
 * Renders a component's children and groups consecutive entries, so they
 * share one <dl> grid while layouts render between them. Children that
 * render nothing (hidden, unauthorized, empty) are dropped.
 */
final class Groups
{
    /**
     * @param  list<int>|null  $only  Child indexes to render (default: all).
     * @return list<array{type: 'entries'|'layout', html: list<string>}>
     */
    public static function from(Children $children, ?array $only = null): array
    {
        /** @var list<array{type: 'entries'|'layout', html: list<string>}> $groups */
        $groups = [];

        foreach ($children->nodes() as $index => $node) {
            if ($only !== null && ! in_array($index, $only, true)) {
                continue;
            }

            $html = $children->render($index)->toHtml();

            if (trim($html) === '') {
                continue;
            }

            $type = $node->component instanceof Entry ? 'entries' : 'layout';
            $last = count($groups) - 1;

            if ($type === 'entries' && $last >= 0 && $groups[$last]['type'] === 'entries') {
                $groups[$last]['html'][] = $html;
            } else {
                $groups[] = ['type' => $type, 'html' => [$html]];
            }
        }

        return $groups;
    }
}
