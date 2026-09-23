<?php

namespace App\Tenancy;

use LogicException;

/**
 * Thrown when tenant-scoped data is touched with no tenant in context.
 *
 * This is a programming error, never a client error: the fix is to establish the
 * context (ResolveTenant for HTTP, TenantContext::run() for jobs and commands),
 * not to catch this exception.
 */
class MissingTenantContext extends LogicException
{
    public function __construct()
    {
        parent::__construct('Tenant-scoped data was accessed without a tenant context.');
    }
}
