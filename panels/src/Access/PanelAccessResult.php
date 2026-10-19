<?php

declare(strict_types=1);

namespace Gcego\Panels\Access;

enum PanelAccessResult: string
{
    case Allowed = 'allowed';

    /** The panel declares neither requirements nor an access policy (deny by default). */
    case Undeclared = 'undeclared';

    case RequirementsDenied = 'requirements_denied';

    case PolicyDenied = 'policy_denied';

    /** Deciding failed (an exception): denied. */
    case Failed = 'failed';
}
