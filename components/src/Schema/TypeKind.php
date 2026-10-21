<?php

declare(strict_types=1);

namespace Gcego\Components\Schema;

enum TypeKind: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Array = 'array';
    case List = 'list';
    case Mixed = 'mixed';
    case Enum = 'enum';
    case ClassString = 'class-string';
    case Instance = 'instance';
}
