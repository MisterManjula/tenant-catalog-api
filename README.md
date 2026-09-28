# tenant-catalog-api

[![CI](https://github.com/MisterManjula/tenant-catalog-api/actions/workflows/ci.yml/badge.svg)](https://github.com/MisterManjula/tenant-catalog-api/actions/workflows/ci.yml)

A multi-tenant catalog API where tenant isolation fails closed and publishing is
idempotent.

## The problem

Several tenants (brands) share one schema: every `products`, `channels` and
`publications` row carries a `tenant_id`. In that shape of database, one query built
without a tenant filter is a data leak between customers, and it is an easy mistake
to make and an easy one to miss in review. Separately, publishing a catalog has to be
safe to retry — a client that times out and retries the same request must not create
two publications, and a channel that is slow to answer must not receive the same
delivery twice.

This repo is a small, complete answer to both problems, built with the idioms of the
framework rather than around them.

## The three decisions

- **Tenant isolation is enforced by a global scope, and it fails closed.** A query
  run with no tenant in context throws, instead of returning every tenant's rows or
  silently returning none. A resource belonging to another tenant answers 404, not
  403.
  → [ADR-001](docs/adr/ADR-001-tenant-isolation.md)
- **Idempotency keys live in PostgreSQL, not Redis.** The `UNIQUE (tenant_id, key)`
  constraint is the lock; a cache is allowed to evict entries under pressure, and an
  evicted key is a guarantee lost in silence.
  → [ADR-002](docs/adr/ADR-002-idempotency-keys-in-postgresql.md)
- **Publishing fans out one job per channel, at-least-once.** A failing channel never
  blocks delivery to the others; a unique constraint absorbs a repeated fan-out, and
  retries are only attempted where retrying could plausibly help.
  → [ADR-003](docs/adr/ADR-003-one-job-per-channel-delivery.md)

## Stack

Laravel 13 (PHP 8.4), PostgreSQL 16, Redis 7, Sanctum, Pest, Larastan (level max),
Pint. Runtime is php-fpm behind nginx, with a separate queue worker.

## Run it

```bash
docker compose up -d --build
```

Wait for `/up` to answer, then seed two demo tenants (Alder Grill, Birch Pizza), each
with priced and unpriced products and a mix of active/inactive channels:

```bash
docker compose exec app php artisan db:seed
```

The seeder is not meant to run twice against the same database — it always creates
new rows. Print a fresh Sanctum token for each tenant's demo user:

```bash
docker compose exec app php artisan app:demo-tokens
```

Use one of those tokens as a bearer token below (`TOKEN`).

**Create a product:**

```bash
curl http://localhost:8080/api/products \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sku": "BURGER-002", "name": "Double smash", "price_cents": 1550}'
```

**Publish the catalog, with an idempotency key:**

```bash
curl http://localhost:8080/api/publications \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: publish-2026-09-28" \
  -d ''
```

The response lists `items` (the priced products that were published) and `withheld`
(the SKUs of any active product whose price was not resolved — `SPECIAL-001` in the
seed data). Each active channel gets its own queued delivery; watch them land with
`docker compose logs echo -f`.

**Repeat the exact same request** — same key, same (empty) body:

```bash
curl -i http://localhost:8080/api/publications \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: publish-2026-09-28" \
  -d ''
```

The response is the same publication data, with an `Idempotent-Replayed:
true` header — no second publication was created.

## Tests

```bash
docker compose exec app php artisan test
```

Tests run against a real PostgreSQL database (`catalog_test`), never SQLite: the
behavior a few of them rely on — unique constraint violations under concurrent
inserts — is part of the contract, not an implementation detail SQLite happens to
share.

## Out of scope

Declared here on purpose, as a judgment call rather than a gap someone has to
discover:

- **HMAC-signed deliveries** — belongs in the Go dispatcher of a sibling project, not
  duplicated here.
- **Horizon** — the queue is small enough that `queue:work` is enough to demonstrate
  the retry and fail-closed behavior.
- **Rate limiting.**
- **Pruning expired idempotency keys** — see the stated gap in
  [ADR-002](docs/adr/ADR-002-idempotency-keys-in-postgresql.md).
- **OpenAPI documentation.**
- **A UI.** This is an API; Sanctum issues bearer tokens only, no sessions.
