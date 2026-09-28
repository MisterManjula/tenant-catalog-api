# ADR-001 — Tenant isolation via a global scope that fails closed

## Context

Several tenants share the same schema: `products`, `channels`, `publications` and
related tables all carry a `tenant_id` column. A shared schema is far cheaper to run
and migrate than a database (or schema) per tenant, and this project's scope does not
justify that cost. The risk of a shared schema is well known: one query built without
a `WHERE tenant_id = ?` clause is a cross-tenant data leak, and that mistake is easy to
make and easy to miss in review.

We also have to decide what a request for another tenant's resource should look like
from the outside, and what should happen when tenant-scoped code runs with no tenant
context at all — the case that actually happened during development, when a queued
job read a product before its context was set up.

## Decision

- **Shared schema, not database-per-tenant.** Every tenant-scoped table has a
  `tenant_id` foreign key. Isolation is enforced in code, not by infrastructure
  boundaries.
- **A global Eloquent scope (`TenantScope`) filters every query**, applied through a
  `BelongsToTenant` trait used by `Product`, `Channel` and `Publication`. The trait
  also sets `tenant_id` from the context on `creating`, unconditionally — a client
  cannot set it via mass assignment (`tenant_id` is never fillable) or by any other
  means.
- **The scope throws `MissingTenantContext` when no tenant is set**, instead of
  querying with no filter (a leak) or returning an empty result (a silent, confusing
  failure that looks like "no data" rather than "broken"). This is deliberately a
  `LogicException`, not a client-facing error: it means the request pipeline or a job
  was wired incorrectly, and the fix is to establish the context, not to catch the
  exception.
- **A resource belonging to another tenant answers 404, not 403.** Route model
  binding queries through the same scope, so a product ID that exists but belongs to
  another tenant is indistinguishable, from the outside, from an ID that does not
  exist at all. A 403 would confirm the ID is valid and just off-limits, which is
  itself information an attacker can use.
- **The tenant context is not just "the logged-in user's tenant".** `TenantContext`
  is a small scoped service with an explicit `run(tenantId, callback)` method. HTTP
  requests get it from `ResolveTenant`, a middleware that runs right after
  authentication. Queued jobs and the queued listener get it explicitly, because a
  queue worker has no authenticated user — this is the classic Laravel trap: code
  that works fine behind `auth:sanctum` starts throwing `MissingTenantContext` the
  moment it runs from a job, because there is nothing to authenticate. The fix is not
  to relax the scope; it is to pass `tenant_id` explicitly into the job and open the
  context there.

## Consequences

- Every tenant-scoped model automatically excludes other tenants' rows, including in
  relations, `count()`, and route model binding — there is exactly one place
  (`TenantScope`) where this logic lives, instead of a `where('tenant_id', ...)`
  repeated at every call site and inevitably forgotten somewhere.
- Code that needs to bypass the scope (there is none in this codebase, and there
  should not be without a strong reason) would have to do so explicitly with
  `withoutGlobalScope`, which is visible in review.
- Any code path that touches a tenant-scoped model without first opening a tenant
  context will throw immediately, in development and in tests — the isolation bug
  surfaces as a stack trace, not as a leaked row noticed later. This is the trade-off
  of failing closed: a missing context is now impossible to service by accident, but
  every new entry point (a new job, a new Artisan command, a scheduled task) has to
  remember to open one.
- The `users` table is deliberately **not** tenant-scoped: a user is what
  `ResolveTenant` uses to establish the context in the first place, so scoping it
  would be circular. This is a conscious exception to the rule, not an oversight.
