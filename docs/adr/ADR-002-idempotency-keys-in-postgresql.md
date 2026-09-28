# ADR-002 — Idempotency keys in PostgreSQL, not Redis

## Context

`POST /api/publications` must be safe to retry: a client that times out waiting for a
response, or whose connection drops after the server has already committed, needs a
way to ask "did this already happen?" instead of blindly retrying and risking a
duplicate publication. The standard mechanism is a client-supplied `Idempotency-Key`
header, remembered server-side for long enough to answer a retry with the original
response instead of running the request again.

The natural place to remember a short-lived key looks like a cache: `Cache::lock()`
or a plain Redis key with a TTL. But a cache is explicitly allowed to evict entries
under memory pressure, and Redis is also our queue and cache backend in this project —
under load, exactly the situation where idempotency matters most, is when eviction is
most likely. An evicted idempotency key does not fail loudly; it just silently stops
protecting anything, and a retried request goes through twice.

## Decision

- **Idempotency keys live in a PostgreSQL table (`idempotency_keys`)**, not in Redis,
  with `UNIQUE (tenant_id, key)`. A database row does not get evicted for memory
  pressure — if the row exists, the guarantee holds until something in this table's
  own lifecycle removes it.
- **The unique constraint is the lock.** `EnsureIdempotency` inserts a row with status
  `in_flight` first (`createOrFirst`, which uses a savepoint under PostgreSQL). Of two
  concurrent requests with the same key, exactly one wins the insert; the other reads
  back the row the winner created. There is no separate locking step to get wrong.
- **Only successful (2xx) responses are stored as `completed`.** Any other outcome —
  a 4xx, a 5xx, or an exception — deletes the row instead of marking it failed. This
  is deliberate: it means the same key can be reused after a failed attempt, and a
  changed request body against a *stored* `completed` key is a real conflict (422,
  "same key, different payload"), while a changed body after a prior *failure* is not
  a conflict at all, because nothing was kept. A consequence worth stating plainly:
  the same key can legitimately produce two different outcomes over time if the
  underlying data changed between a failed attempt and a later one — idempotency
  guarantees "the same request is not repeated", not "this key can never be reused."
- **Tests run against real PostgreSQL, never SQLite** (already required for the whole
  suite, but doubly so here): the exact behavior of a unique-constraint violation
  under concurrent inserts, and the savepoint semantics `createOrFirst` depends on,
  are part of what this feature relies on, and SQLite's locking model does not
  reproduce them.

## Consequences

- The idempotency guarantee survives a busy cache and a restarted Redis. It does not
  survive a lost database row, but nothing in normal operation deletes a `completed`
  row — there is no pruning job in this project (see "out of scope" in the README),
  so keys accumulate indefinitely. A production version of this would need a
  scheduled cleanup once keys are older than the client's realistic retry window.
- A request that crashes the process between inserting the `in_flight` row and
  reaching a final response leaves that row stuck at `in_flight` forever — every
  retry with the same key gets 409, permanently, with no automatic recovery. This is
  a known gap, not handled here; a production version would need either a staleness
  timeout on `in_flight` rows or a manual unlock path.
- Every idempotency check costs at least one extra round trip to PostgreSQL per
  request on this endpoint. That is an acceptable, deliberate cost for a low-volume
  "publish the catalog" action; it would not be the right default for a
  high-frequency endpoint.
