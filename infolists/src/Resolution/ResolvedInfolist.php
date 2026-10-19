<?php

declare(strict_types=1);

namespace Gcego\Infolists\Resolution;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Data\Source;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Schema\InfolistSchema;

/**
 * An infolist found inside a registered blueprint (never from client input).
 */
final readonly class ResolvedInfolist
{
    public function __construct(
        public string $definition,
        public BlueprintInterface $blueprint,
        public Infolist $infolist,
        public string $path,
        public Source $source,
        public InfolistSchema $schema,
    ) {}
}
