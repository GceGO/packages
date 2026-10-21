<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\Contracts\BlueprintDeriver;
use Gcego\Blueprints\Contracts\BlueprintInterface;

/**
 * Derives one page definition per Pages reference ("subjects-list-subjects").
 */
final readonly class PagesDeriver implements BlueprintDeriver
{
    public function derive(BlueprintInterface $blueprint): iterable
    {
        foreach ($blueprint->pages() as $class) {
            $short = substr($class, (int) strrpos($class, '\\') + 1);

            yield new PageDefinition($blueprint->name().'-'.strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short)), $class);
        }
    }
}
