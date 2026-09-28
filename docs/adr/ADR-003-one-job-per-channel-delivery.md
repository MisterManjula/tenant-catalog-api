# ADR-003 — One job per channel, at-least-once delivery

## Context

Publishing a catalog has to reach every active channel of a tenant (app, kiosk,
delivery), and channels are independent, unreliable, third-party HTTP endpoints we do
not control. A single "deliver to everyone" job would mean one slow or failing
channel holds up, or fails, delivery to every other channel — a fault in one
integration should never become a fault in all of them.

Laravel's queue guarantees **at-least-once** execution: a job can run more than once
(a worker crashes after finishing the work but before acknowledging the job, for
example), never less than once. Anything downstream of a dispatch has to be written
assuming duplicate execution is possible, not just theoretically but as a normal
operating condition.

## Decision

- **One `DeliverPublication` job per (publication, channel) pair.** The listener
  `CreateChannelDeliveries` fans a publication out to every active channel of its
  tenant, creating one `channel_deliveries` row and dispatching one job per channel.
  A failure delivering to the kiosk channel has no effect on the app or delivery
  channels.
- **`UNIQUE (publication_id, channel_id)` absorbs duplicate fan-out.** The listener
  itself is queued and therefore also at-least-once: if it runs twice for the same
  publication, `createOrFirst` on the unique pair means the second run finds the
  existing delivery rows and dispatches no new jobs. The database constraint, not
  listener-side bookkeeping, is what makes a repeated run safe.
- **`X-Delivery-Id` lets the receiver deduplicate on their end.** The job can retry
  the same channel multiple times (backoff below); the header carries the
  `channel_deliveries.id`, so a channel that received the payload once but failed to
  answer can recognize a retry as the same delivery rather than a new one.
- **Retries and failure are split by whether retrying could plausibly help.**
  `$tries = 5` with backoff `[10, 30, 90, 270]` seconds. A 5xx, a 429, or a connection
  timeout throws, and the queue retries with that backoff — these are conditions
  where the channel, or the network, might recover. Any other 4xx calls `$this->fail()`
  immediately, with no retry: a 400 or a 422 means the channel rejected this specific
  request, and repeating the exact same request will not change that outcome. Once
  retries are exhausted (or after an immediate `fail()`), `failed()` marks the
  delivery `failed` and records `last_error`.
- **The event carries IDs, not models.** `CatalogPublished` is dispatched
  `ShouldDispatchAfterCommit` and carries `tenantId` and `publicationId`, not a
  `Publication` instance. A serialized Eloquent model restored in the worker would be
  re-queried before any tenant context exists — exactly the `MissingTenantContext`
  trap described in ADR-001. The listener and the job both open the tenant context
  themselves, from the plain ID, before touching any tenant-scoped model.

## Consequences

- A channel outage is isolated to that channel's deliveries; the rest of the fan-out
  proceeds and is visible independently in `channel_deliveries`.
- The unique constraint means at-least-once execution of the *listener* is safe by
  construction, but it does not cover every failure window: if the process crashes
  after the delivery row is inserted but before the job is dispatched, that delivery
  is left `pending` with no job ever queued for it, and nothing currently detects or
  retries that state. A production version would need an outbox pattern or a periodic
  sweep for stale `pending` deliveries; this project accepts the gap and states it
  here rather than building that machinery for a weekend-scoped repo.
- Distinguishing "retry" from "fail" by status code is a simplification: a channel
  that returns 400 for a transient reason (a bug on their end, not the request) is
  treated as permanently rejected. This mirrors real webhook conventions closely
  enough to be a reasonable default, not a guarantee that every 4xx is truly
  unrecoverable.
