<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A destination for published catalogs (app, kiosk, delivery platform).
 */
#[Fillable(['name', 'webhook_url', 'active'])]
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ChannelDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ChannelDelivery::class);
    }
}
