<?php

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Run the callback as the given tenant, e.g. to create tenant-scoped fixtures.
 *
 * @template TResult
 *
 * @param  callable(): TResult  $callback
 * @return TResult
 */
function asTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, $callback);
}
