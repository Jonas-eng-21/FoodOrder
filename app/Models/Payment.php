<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An approved (simulated) payment. Rejected attempts are never persisted. */
class Payment extends Model
{
    protected $fillable = ['order_id', 'method', 'total_cents', 'received_cents', 'change_cents', 'simulated_reference'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'total_cents' => 'integer',
            'received_cents' => 'integer',
            'change_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
