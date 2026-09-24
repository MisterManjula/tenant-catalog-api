<?php

use App\Enums\DeliveryStatus;
use App\Events\CatalogPublished;
use App\Exceptions\DeliveryFailed;
use App\Jobs\DeliverPublication;
use App\Listeners\CreateChannelDeliveries;
use App\Models\Channel;
use App\Models\ChannelDelivery;
use App\Models\Product;
use App\Models\Publication;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * A publication with one delivery to a single channel, ready for DeliverPublication.
 */
function pendingDelivery(Tenant $tenant): ChannelDelivery
{
    return asTenant($tenant, function (): ChannelDelivery {
        Product::factory()->create();
        $channel = Channel::factory()->create(['webhook_url' => 'https://kiosk.example.test/hook']);
        $publication = Publication::create();

        return $publication->deliveries()->create(['channel_id' => $channel->id]);
    });
}

it('queues exactly one delivery job per active channel, even if the listener runs twice', function () {
    // Only the delivery job is faked: the queued listener still runs (sync queue in tests).
    Queue::fake([DeliverPublication::class]);

    $alder = Tenant::factory()->create(['name' => 'Alder Grill']);
    $birch = Tenant::factory()->create(['name' => 'Birch Pizza']);
    asTenant($alder, function () {
        Product::factory()->create();
        Channel::factory()->count(3)->create();
        Channel::factory()->inactive()->create();
    });
    asTenant($birch, fn () => Channel::factory()->create());

    Sanctum::actingAs(User::factory()->for($alder)->create());
    postJson('/api/publications', [], ['Idempotency-Key' => 'publish-1'])->assertCreated();

    Queue::assertPushed(DeliverPublication::class, 3);

    // At-least-once: a second run of the listener must not fan out again.
    $publicationId = asTenant($alder, fn () => Publication::query()->sole()->id);
    app(CreateChannelDeliveries::class)->handle(new CatalogPublished($alder->id, $publicationId));

    Queue::assertPushed(DeliverPublication::class, 3);
    expect(ChannelDelivery::query()->count())->toBe(3);
});

it('throws on a 5xx so the queue retries the delivery', function () {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);
    $tenant = Tenant::factory()->create();
    $delivery = pendingDelivery($tenant);

    $job = new DeliverPublication($tenant->id, $delivery->id);

    expect(fn () => $job->handle(app(TenantContext::class)))->toThrow(DeliveryFailed::class);
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Pending);
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Delivery-Id', (string) $delivery->id));
});

it('fails a delivery on a 4xx without retrying it', function () {
    Http::fake(['*' => Http::response('Bad Request', 400)]);
    $tenant = Tenant::factory()->create();
    $delivery = pendingDelivery($tenant);

    DeliverPublication::dispatchSync($tenant->id, $delivery->id);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_error)->toContain('400');
    Http::assertSentCount(1);
});
