<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An immutable snapshot of a tenant's catalog at publish time.
 */
#[Fillable(['withheld'])]
class Publication extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'withheld' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'withheld' => 'array',
        ];
    }

    /**
     * @return HasMany<PublicationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PublicationItem::class);
    }

    /**
     * @return HasMany<ChannelDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ChannelDelivery::class);
    }
}
