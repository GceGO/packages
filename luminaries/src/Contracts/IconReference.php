<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Contracts;

use BackedEnum;

/**
 * A typed icon identifier: a string-backed enum whose case values are icon
 * names Luminaries resolves ("heroicon:home", "navigation.students").
 *
 * The bundled Icons\* enums implement it; an application implements it on
 * its own enums of aliases so they are accepted wherever an icon is:
 *
 *     enum SchoolIcon: string implements IconReference
 *     {
 *         case Terms = 'resources.terms';
 *     }
 */
interface IconReference extends BackedEnum {}
