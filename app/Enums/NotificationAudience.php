<?php

namespace App\Enums;

/** Internal audiences of the notification records (no real recipients exist). */
enum NotificationAudience: string
{
    case Customer = 'customer';
    case Kitchen = 'kitchen';
}
