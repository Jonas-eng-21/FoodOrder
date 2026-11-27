<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'base_price_cents', 'active'];

    protected function casts(): array
    {
        return [
            'base_price_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Price in reais (e.g. "R$ 20,00"), derived with integer arithmetic only.
     *
     * @return Attribute<string, never>
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            'R$ %s,%02d',
            number_format(intdiv($this->base_price_cents, 100), 0, '', '.'),
            $this->base_price_cents % 100,
        ));
    }
}
