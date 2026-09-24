<?php

namespace App\Models;

use App\Enums\IdempotencyStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One client-supplied Idempotency-Key per tenant. The row itself is the lock:
 * UNIQUE (tenant_id, key) lets exactly one request insert it.
 */
#[Fillable(['key', 'request_hash', 'status', 'response_status', 'response_body'])]
class IdempotencyKey extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_status' => 'integer',
            'response_body' => 'array',
        ];
    }
}
