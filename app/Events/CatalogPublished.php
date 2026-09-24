<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched inside the publish transaction, delivered only after it commits:
 * a listener must never see a publication that could still be rolled back.
 *
 * Carries ids, not models: the publication is tenant-scoped, so restoring a
 * serialized model in the worker would query it before any tenant context exists.
 */
class CatalogPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $publicationId,
    ) {}
}
