<?php

namespace App\Enums;

enum IdempotencyStatus: string
{
    case InFlight = 'in_flight';
    case Completed = 'completed';
}
