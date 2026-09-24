<?php

namespace App\Listeners;

use App\Events\CatalogPublished;
use App\Jobs\DeliverPublication;
use App\Models\Channel;
use App\Models\Publication;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fans a publication out to every active channel of its tenant: one delivery row
 * and one job per channel, so a failing channel never holds back the others.
 *
 * Queued listeners run at least once. The UNIQUE (publication_id, channel_id)
 * constraint makes a second run a no-op: createOrFirst() returns the existing row,
 * and a job is dispatched only for rows this run actually created.
 */
class CreateChannelDeliveries implements ShouldQueue
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(CatalogPublished $event): void
    {
        $this->context->run($event->tenantId, function () use ($event): void {
            $publication = Publication::query()->findOrFail($event->publicationId);

            Channel::query()->where('active', true)->orderBy('id')->each(
                function (Channel $channel) use ($publication, $event): void {
                    $delivery = $publication->deliveries()->createOrFirst(['channel_id' => $channel->id]);

                    if ($delivery->wasRecentlyCreated) {
                        DeliverPublication::dispatch($event->tenantId, $delivery->id);
                    }
                },
            );
        });
    }
}
