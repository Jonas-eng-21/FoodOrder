<?php

namespace App\Enums;

/**
 * Persisted order status identifiers. Only the initial status exists in US03;
 * the remaining ones arrive with the State pattern (US05/US06).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
}
