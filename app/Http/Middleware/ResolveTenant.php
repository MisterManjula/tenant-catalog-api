<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant context from the authenticated user. Runs right after
 * auth:sanctum and before route model binding (see the priority list in
 * bootstrap/app.php), so bound models are already tenant-scoped.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $this->context->run($user->tenant_id, fn () => $next($request));
    }
}
