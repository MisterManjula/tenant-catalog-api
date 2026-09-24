<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class DeliveryFailed extends RuntimeException
{
    public static function fromResponse(Response $response): self
    {
        return new self("Channel answered HTTP {$response->status()}.");
    }
}
