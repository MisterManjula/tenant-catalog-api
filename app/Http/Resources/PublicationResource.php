<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Publication
 */
class PublicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            // SKUs left out because their price was not resolved: the caller must see them.
            'withheld' => $this->withheld,
            'items' => PublicationItemResource::collection($this->whenLoaded('items')),
            'deliveries' => ChannelDeliveryResource::collection($this->whenLoaded('deliveries')),
        ];
    }
}
