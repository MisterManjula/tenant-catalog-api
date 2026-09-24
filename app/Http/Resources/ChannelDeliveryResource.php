<?php

namespace App\Http\Resources;

use App\Models\ChannelDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChannelDelivery
 */
class ChannelDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'updated_at' => $this->updated_at,
        ];
    }
}
