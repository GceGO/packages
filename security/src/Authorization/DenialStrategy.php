<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

/**
 * How a denial is answered.
 *
 * Forbidden (403): the user learns the resource exists.
 * Conceal (404): used for records and tenants outside the user's scope, so
 * identifiers of other institutions' data reveal nothing. It is chosen
 * explicitly per check; denials are not converted to 404 automatically.
 */
enum DenialStrategy: int
{
    case Forbidden = 403;
    case Conceal = 404;
}
