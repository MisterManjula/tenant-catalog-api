<?php

namespace App\Http\Resources;

use App\Models\PublicationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicationItem
 */
class PublicationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'price_cents' => $this->price_cents,
        ];
    }
}
