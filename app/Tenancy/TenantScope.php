<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query to the tenant in context, and fails closed without one:
 * no context means an exception, never "all rows" and never "no rows".
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Resolved on every query, not captured once: global scopes are registered
        // once per process, while the context changes per request and per job.
        $tenantId = app(TenantContext::class)->id();

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
