<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Two demo tenants, each with priced and unpriced products and a mix of active and
 * inactive channels. Channels point at the `echo` compose service: a real HTTP
 * endpoint that just logs whatever it receives, so deliveries are visible without
 * standing up a fake app, kiosk or delivery integration.
 *
 * Deliberately does not use WithoutModelEvents: BelongsToTenant sets tenant_id from
 * the `creating` event, so disabling model events would seed every product and
 * channel with a null tenant_id and crash the NOT NULL constraint.
 */
class DatabaseSeeder extends Seeder
{
    private const ECHO_WEBHOOK_URL = 'http://echo:8080';

    public function run(): void
    {
        $this->tenant('Alder Grill', 'owner@aldergrill.test');
        $this->tenant('Birch Pizza', 'owner@birchpizza.test');
    }

    private function tenant(string $name, string $email): void
    {
        $tenant = Tenant::factory()->create(['name' => $name]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name.' Owner',
            'email' => $email,
        ]);

        app(TenantContext::class)->run($tenant->id, function () {
            Product::factory()->create(['sku' => 'BURGER-001', 'name' => 'Smash burger', 'price_cents' => 1250]);
            Product::factory()->create(['sku' => 'FRIES-001', 'name' => 'Fries', 'price_cents' => 450]);
            // Price not resolved upstream: withheld from every publication (see ADR-001).
            Product::factory()->unpriced()->create(['sku' => 'SPECIAL-001', 'name' => "Chef's special"]);

            Channel::factory()->create(['name' => 'app', 'webhook_url' => self::ECHO_WEBHOOK_URL]);
            Channel::factory()->create(['name' => 'kiosk', 'webhook_url' => self::ECHO_WEBHOOK_URL]);
            Channel::factory()->inactive()->create(['name' => 'delivery', 'webhook_url' => self::ECHO_WEBHOOK_URL]);
        });
    }
}
