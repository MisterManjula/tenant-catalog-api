<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\MissingTenantContext;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('answers 404, not 403, for a product of another tenant', function () {
    $alder = Tenant::factory()->create(['name' => 'Alder Grill']);
    $birch = Tenant::factory()->create(['name' => 'Birch Pizza']);
    $birchProduct = asTenant($birch, fn () => Product::factory()->create());

    Sanctum::actingAs(User::factory()->for($alder)->create());
    getJson("/api/products/{$birchProduct->id}")->assertNotFound();

    // Same URL, owning tenant: the product exists, so the 404 above is the scope at work.
    Sanctum::actingAs(User::factory()->for($birch)->create());
    getJson("/api/products/{$birchProduct->id}")->assertOk();
});

it('never lists products of another tenant', function () {
    $alder = Tenant::factory()->create(['name' => 'Alder Grill']);
    $birch = Tenant::factory()->create(['name' => 'Birch Pizza']);
    $alderSkus = asTenant($alder, fn () => Product::factory()->count(2)->create()->pluck('sku')->all());
    asTenant($birch, fn () => Product::factory()->count(3)->create());

    Sanctum::actingAs(User::factory()->for($alder)->create());

    expect(getJson('/api/products')->assertOk()->json('data.*.sku'))
        ->toEqualCanonicalizing($alderSkus);
});

it('takes tenant_id from the context, never from the input', function () {
    $alder = Tenant::factory()->create(['name' => 'Alder Grill']);
    $birch = Tenant::factory()->create(['name' => 'Birch Pizza']);

    $product = asTenant($alder, fn () => Product::create([
        'tenant_id' => $birch->id,
        'sku' => 'ALD-001',
        'name' => 'Smash burger',
        'price_cents' => 1250,
    ]));

    expect($product->tenant_id)->toBe($alder->id);
});

// The most important test in the repo: a query with no tenant context must fail
// loudly. Returning every row would be a data leak, returning none would hide the bug.
it('throws instead of querying when there is no tenant context', function () {
    Product::query()->get();
})->throws(MissingTenantContext::class);
