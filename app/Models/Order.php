<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Patterns\Decorator\MenuItemDecorator;
use App\Patterns\State\OrderState;
use App\Patterns\State\PaidState;
use App\Patterns\State\PendingState;
use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** Status is intentionally not fillable: only the server assigns it. */
    protected $fillable = ['product_id', 'product_name_snapshot', 'extras_snapshot', 'subtotal_cents'];

    protected function casts(): array
    {
        return [
            'extras_snapshot' => 'array',
            'subtotal_cents' => 'integer',
            'status' => OrderStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** State pattern — context: rehydrates the State object that matches the persisted status. */
    public function state(): OrderState
    {
        return match ($this->status) {
            OrderStatus::Pending => new PendingState,
            OrderStatus::Paid => new PaidState,
        };
    }

    public function canBePaid(): bool
    {
        return $this->state()->canPay();
    }

    /** Persists the status of the given (already validated) next state. */
    public function transitionTo(OrderState $state): void
    {
        $this->status = $state->status();
        $this->save();
    }

    /**
     * Description rebuilt from the stored snapshots, never from the current catalog.
     *
     * @return Attribute<string, never>
     */
    protected function description(): Attribute
    {
        return Attribute::get(fn (): string => implode(
            MenuItemDecorator::SEPARATOR,
            [$this->product_name_snapshot, ...array_column($this->extras_snapshot, 'name')],
        ));
    }

    /** @return Attribute<string, never> */
    protected function formattedSubtotal(): Attribute
    {
        return Attribute::get(fn (): string => Money::format($this->subtotal_cents));
    }
}
