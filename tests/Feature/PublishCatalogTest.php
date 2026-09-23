<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\postJson;

it('withholds products with an unresolved price instead of publishing them', function () {
    $alder = Tenant::factory()->create(['name' => 'Alder Grill']);
    asTenant($alder, function () {
        Product::factory()->create(['sku' => 'ALD-001', 'price_cents' => 1250]);
        Product::factory()->create(['sku' => 'ALD-002', 'price_cents' => 890]);
        Product::factory()->unpriced()->create(['sku' => 'ALD-003']);
        Product::factory()->inactive()->create(['sku' => 'ALD-004']);
    });

    Sanctum::actingAs(User::factory()->for($alder)->create());

    $response = postJson('/api/publications')
        ->assertCreated()
        ->assertJsonPath('data.withheld', ['ALD-003']);

    expect($response->json('data.items.*.sku'))->toBe(['ALD-001', 'ALD-002']);
    assertDatabaseMissing('publication_items', ['sku' => 'ALD-003']);
});
