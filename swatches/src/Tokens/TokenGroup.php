<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tokens;

enum TokenGroup: string
{
    case Surface = 'surface';
    case Border = 'border';
    case Brand = 'brand';
    case Status = 'status';
    case State = 'state';
    case Navigation = 'navigation';
    case Chart = 'chart';
    case Legacy = 'legacy';
}
