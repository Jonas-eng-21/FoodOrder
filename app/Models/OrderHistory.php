<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A recorded state transition. It is a historical fact: created once, never updated. */
class OrderHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'previous_status', 'new_status'];

    protected function casts(): array
    {
        return [
            'previous_status' => OrderStatus::class,
            'new_status' => OrderStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
