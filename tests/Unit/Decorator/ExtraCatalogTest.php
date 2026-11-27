<?php

namespace Tests\Unit\Decorator;

use App\Patterns\Decorator\ExtraCatalog;
use Tests\TestCase;

class ExtraCatalogTest extends TestCase
{
    private function catalog(): ExtraCatalog
    {
        return new ExtraCatalog(config('foodorder.extras'));
    }

    public function test_catalog_defines_the_three_extras_with_prices_in_cents(): void
    {
        $catalog = $this->catalog();

        $this->assertSame(['bacon', 'cheese', 'egg'], $catalog->codes());
        $this->assertSame(400, $catalog->find('bacon')->priceCents);
        $this->assertSame('Queijo extra', $catalog->find('cheese')->name);
        $this->assertSame(200, $catalog->find('egg')->priceCents);
        $this->assertNull($catalog->find('ketchup'));
    }

    public function test_burger_and_hotdog_allow_all_extras_and_fries_none(): void
    {
        $catalog = $this->catalog();

        $this->assertSame(['bacon', 'cheese', 'egg'], array_keys($catalog->allowedFor('burger')));
        $this->assertSame(['bacon', 'cheese', 'egg'], array_keys($catalog->allowedFor('hotdog')));
        $this->assertSame([], $catalog->allowedFor('fries'));
        $this->assertSame([], $catalog->allowedFor('unknown'));
    }
}
