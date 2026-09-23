<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Publication;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots the current tenant's active catalog into an immutable publication.
 *
 * Fails closed on prices: a product whose price was not resolved upstream is
 * withheld (left out of the snapshot and reported by SKU) rather than published
 * with a guessed or zero price.
 */
class PublishCatalog
{
    public function handle(): Publication
    {
        return DB::transaction(function (): Publication {
            $active = Product::query()->where('active', true)->orderBy('sku')->get();
            $priced = $active->whereNotNull('price_cents');
            $unpriced = $active->whereNull('price_cents');

            $publication = Publication::create([
                'withheld' => $unpriced->pluck('sku')->values()->all(),
            ]);

            $publication->items()->createMany(
                $priced->map(fn (Product $product): array => [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'price_cents' => $product->price_cents,
                ])->all(),
            );

            return $publication;
        });
    }
}
