<?php

use App\Enums\IdempotencyStatus;
use App\Http\Idempotency\RequestFingerprint;
use App\Models\IdempotencyKey;
use App\Models\Product;
use App\Models\Publication;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * A tenant with one priced product, authenticated as one of its users.
 */
function actingAsPublisher(string $name): Tenant
{
    $tenant = Tenant::factory()->create(['name' => $name]);
    asTenant($tenant, fn () => Product::factory()->create());
    Sanctum::actingAs(User::factory()->for($tenant)->create());

    return $tenant;
}

it('replays the stored response for the same key and body', function () {
    $alder = actingAsPublisher('Alder Grill');
    $headers = ['Idempotency-Key' => 'publish-2026-09-24'];

    $first = postJson('/api/publications', ['reason' => 'menu update'], $headers)->assertCreated();
    $replay = postJson('/api/publications', ['reason' => 'menu update'], $headers)->assertCreated();

    $replay->assertHeader('Idempotent-Replayed', 'true');
    expect($replay->json())->toEqual($first->json())
        ->and(asTenant($alder, fn () => Publication::query()->count()))->toBe(1);
});

it('rejects the same key with a different body', function () {
    actingAsPublisher('Alder Grill');
    $headers = ['Idempotency-Key' => 'publish-2026-09-24'];

    postJson('/api/publications', ['reason' => 'menu update'], $headers)->assertCreated();
    postJson('/api/publications', ['reason' => 'price fix'], $headers)->assertUnprocessable();
});

it('answers 409 while a request with the same key is still in flight', function () {
    $alder = actingAsPublisher('Alder Grill');
    asTenant($alder, fn () => IdempotencyKey::create([
        'key' => 'publish-2026-09-24',
        'request_hash' => RequestFingerprint::of('POST', 'api/publications', []),
        'status' => IdempotencyStatus::InFlight,
    ]));

    postJson('/api/publications', [], ['Idempotency-Key' => 'publish-2026-09-24'])->assertConflict();
    expect(asTenant($alder, fn () => Publication::query()->count()))->toBe(0);
});

it('scopes keys per tenant: the same key publishes once for each tenant', function () {
    $headers = ['Idempotency-Key' => 'publish-2026-09-24'];

    actingAsPublisher('Alder Grill');
    $alderId = postJson('/api/publications', [], $headers)->assertCreated()->json('data.id');

    actingAsPublisher('Birch Pizza');
    $birch = postJson('/api/publications', [], $headers)->assertCreated();

    $birch->assertHeaderMissing('Idempotent-Replayed');
    expect($birch->json('data.id'))->not->toBe($alderId);
});
