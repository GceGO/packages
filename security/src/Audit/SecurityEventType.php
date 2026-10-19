<?php

declare(strict_types=1);

namespace Gcego\Security\Audit;

enum SecurityEventType: string
{
    case Denied = 'security.denied';
    case TenantDenied = 'security.tenant_denied';
    case SensitiveAllowed = 'security.sensitive_allowed';
    case OperationRevoked = 'security.operation_revoked';
}
