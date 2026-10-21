<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

use Gcego\Components\Collections\ComponentCollection;

interface HasChildren
{
    public function children(): ComponentCollection;

    public function childCount(): int;

    public function hasChildren(): bool;
}
