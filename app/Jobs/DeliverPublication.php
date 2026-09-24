<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Exceptions\DeliveryFailed;
use App\Http\Resources\PublicationResource;
use App\Models\Channel;
use App\Models\ChannelDelivery;
use App\Models\Publication;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers one publication to one channel, at least once.
 *
 * Transient failures (5xx, 429, timeouts) throw, so the queue retries with backoff.
 * Other 4xx fail immediately: retrying a request the channel rejected cannot fix it.
 * The receiver deduplicates retries with the X-Delivery-Id header.
 */
class DeliverPublication implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [10, 30, 90, 270];

    /**
     * Hard limit for one attempt; the HTTP timeout below stays well under it.
     */
    public int $timeout = 30;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $deliveryId,
    ) {}

    public function handle(TenantContext $context): void
    {
        $context->run($this->tenantId, function (): void {
            $delivery = ChannelDelivery::query()->findOrFail($this->deliveryId);
            // Channel and publication are tenant-scoped: loading them through the scope fails
            // closed if the delivery does not belong to the tenant this job runs for.
            $channel = Channel::query()->findOrFail($delivery->channel_id);
            $publication = Publication::query()->with('items')->findOrFail($delivery->publication_id);

            $delivery->increment('attempts');

            $response = Http::timeout(10)
                ->withHeaders(['X-Delivery-Id' => (string) $delivery->id])
                ->post($channel->webhook_url, new PublicationResource($publication));

            if ($response->successful()) {
                $delivery->update(['status' => DeliveryStatus::Delivered, 'last_error' => null]);

                return;
            }

            if ($response->serverError() || $response->tooManyRequests()) {
                throw DeliveryFailed::fromResponse($response);
            }

            $this->fail(DeliveryFailed::fromResponse($response));
        });
    }

    /**
     * Called once retries are exhausted, or right away after fail().
     */
    public function failed(?Throwable $exception): void
    {
        // Delivery rows carry no tenant_id and are not scoped: no context needed here.
        ChannelDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => DeliveryStatus::Failed,
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
