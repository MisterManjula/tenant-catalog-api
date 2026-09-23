<?php

namespace App\Tenancy;

/**
 * The tenant the current request or job acts for.
 *
 * Registered as a scoped binding, so the queue worker discards it between jobs.
 */
class TenantContext
{
    private ?int $tenantId = null;

    /**
     * @throws MissingTenantContext
     */
    public function id(): int
    {
        return $this->tenantId ?? throw new MissingTenantContext;
    }

    /**
     * Run the callback as the given tenant, then restore the previous context,
     * even if the callback throws.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(int $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
