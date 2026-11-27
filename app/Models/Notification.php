<?php

namespace App\Models;

use App\Enums\NotificationAudience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An internal notification record (customer or kitchen); nothing is ever sent to anyone. */
class Notification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'event_key', 'audience', 'message'];

    protected function casts(): array
    {
        return ['audience' => NotificationAudience::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
