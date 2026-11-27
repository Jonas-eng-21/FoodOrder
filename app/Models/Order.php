<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Patterns\Decorator\MenuItemDecorator;
use App\Patterns\Observer\OrderEventPublisher;
use App\Patterns\Observer\OrderStateChanged;
use App\Patterns\State\CancelledState;
use App\Patterns\State\DeliveredState;
use App\Patterns\State\OrderState;
use App\Patterns\State\PaidState;
use App\Patterns\State\PendingState;
use App\Patterns\State\PreparingState;
use App\Patterns\State\ReadyState;
use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return HasMany<OrderHistory, $this> transitions in chronological order (id breaks ties) */
    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderBy('created_at')->orderBy('id');
    }

    /** State pattern — context: rehydrates the State object that matches the persisted status. */
    public function state(): OrderState
    {
        return match ($this->status) {
            OrderStatus::Pending => new PendingState,
            OrderStatus::Paid => new PaidState,
            OrderStatus::Preparing => new PreparingState,
            OrderStatus::Ready => new ReadyState,
            OrderStatus::Delivered => new DeliveredState,
            OrderStatus::Cancelled => new CancelledState,
        };
    }

    public function canBePaid(): bool
    {
        return $this->state()->canPay();
    }

    /**
     * The single point where a validated transition is applied: persists the new status and then
     * publishes one OrderStateChanged so the subscribed observers run in the caller's transaction.
     */
    public function transitionTo(OrderState $state, OrderEventPublisher $publisher): void
    {
        $previous = $this->status;

        $this->status = $state->status();
        $this->save();

        $publisher->notify(new OrderStateChanged($this->id, $previous, $this->status));
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

    /**
     * Base product price at creation time, derived from the stored snapshots (subtotal minus extras).
     *
     * @return Attribute<int, never>
     */
    protected function basePriceCents(): Attribute
    {
        return Attribute::get(fn (): int => $this->subtotal_cents - array_sum(array_column($this->extras_snapshot, 'price_cents')));
    }

    /** @return Attribute<string, never> */
    protected function formattedSubtotal(): Attribute
    {
        return Attribute::get(fn (): string => Money::format($this->subtotal_cents));
    }
}
