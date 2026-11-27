<?php

namespace App\Enums;

/**
 * Persisted order status identifiers. Pending and Paid exist so far;
 * the remaining ones arrive with the State pattern (US06).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
