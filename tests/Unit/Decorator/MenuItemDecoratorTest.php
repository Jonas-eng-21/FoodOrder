<?php

namespace Tests\Unit\Decorator;

use App\Patterns\Decorator\BaconDecorator;
use App\Patterns\Decorator\BaseMenuItem;
use App\Patterns\Decorator\CheeseDecorator;
use App\Patterns\Decorator\EggDecorator;
use App\Patterns\Decorator\Extra;
use App\Patterns\Decorator\MenuItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MenuItemDecoratorTest extends TestCase
{
    private function bacon(): Extra
    {
        return new Extra('bacon', 'Bacon', 400, ['burger', 'hotdog']);
    }

    private function cheese(): Extra
    {
        return new Extra('cheese', 'Queijo extra', 300, ['burger', 'hotdog']);
    }

    private function egg(): Extra
    {
        return new Extra('egg', 'Ovo', 200, ['burger', 'hotdog']);
    }

    public function test_base_item_exposes_its_own_price_and_description(): void
    {
        $item = new BaseMenuItem('Hambúrguer', 2000);

        $this->assertInstanceOf(MenuItem::class, $item);
        $this->assertSame(2000, $item->priceCents());
        $this->assertSame('Hambúrguer', $item->description());
    }

    public function test_each_decorator_adds_its_own_contribution(): void
    {
        $base = new BaseMenuItem('Hambúrguer', 2000);

        $bacon = new BaconDecorator($base, $this->bacon());
        $cheese = new CheeseDecorator($base, $this->cheese());
        $egg = new EggDecorator($base, $this->egg());

        $this->assertSame(2400, $bacon->priceCents());
        $this->assertSame('Hambúrguer + Bacon', $bacon->description());
        $this->assertSame(2300, $cheese->priceCents());
        $this->assertSame('Hambúrguer + Queijo extra', $cheese->description());
        $this->assertSame(2200, $egg->priceCents());
        $this->assertSame('Hambúrguer + Ovo', $egg->description());
    }

    public function test_decorators_are_menu_items_and_can_be_chained(): void
    {
        $item = new BaseMenuItem('Hambúrguer', 2000);
        $item = new BaconDecorator($item, $this->bacon());
        $item = new CheeseDecorator($item, $this->cheese());

        $this->assertInstanceOf(MenuItem::class, $item);
        $this->assertSame(2700, $item->priceCents());
        $this->assertSame('Hambúrguer + Bacon + Queijo extra', $item->description());

        $item = new EggDecorator($item, $this->egg());

        $this->assertSame(2900, $item->priceCents());
        $this->assertSame('Hambúrguer + Bacon + Queijo extra + Ovo', $item->description());
    }

    public function test_a_decorator_delegates_to_the_wrapped_component(): void
    {
        $inner = new class implements MenuItem
        {
            public int $priceCalls = 0;

            public int $descriptionCalls = 0;

            public function priceCents(): int
            {
                $this->priceCalls++;

                return 1000;
            }

            public function description(): string
            {
                $this->descriptionCalls++;

                return 'Fake';
            }
        };

        $decorated = new EggDecorator($inner, $this->egg());

        $this->assertSame(1200, $decorated->priceCents());
        $this->assertSame('Fake + Ovo', $decorated->description());
        $this->assertSame(1, $inner->priceCalls);
        $this->assertSame(1, $inner->descriptionCalls);
    }

    public function test_decorating_does_not_modify_the_original_component(): void
    {
        $base = new BaseMenuItem('Hambúrguer', 2000);

        new CheeseDecorator(new BaconDecorator($base, $this->bacon()), $this->cheese());

        $this->assertSame(2000, $base->priceCents());
        $this->assertSame('Hambúrguer', $base->description());
    }

    public function test_the_same_component_can_be_decorated_independently(): void
    {
        $base = new BaseMenuItem('Hambúrguer', 2000);

        $withBacon = new BaconDecorator($base, $this->bacon());
        $withEgg = new EggDecorator($base, $this->egg());

        $this->assertSame(2400, $withBacon->priceCents());
        $this->assertSame(2200, $withEgg->priceCents());
    }

    public function test_composition_order_does_not_change_the_price(): void
    {
        $base = new BaseMenuItem('Cachorro-quente', 1500);

        $one = new EggDecorator(new CheeseDecorator(new BaconDecorator($base, $this->bacon()), $this->cheese()), $this->egg());
        $other = new BaconDecorator(new CheeseDecorator(new EggDecorator($base, $this->egg()), $this->cheese()), $this->bacon());

        $this->assertSame(2400, $one->priceCents());
        $this->assertSame($one->priceCents(), $other->priceCents());
    }

    public function test_prices_are_integers_in_cents(): void
    {
        $item = new BaconDecorator(new BaseMenuItem('Hambúrguer', 2000), $this->bacon());

        $this->assertIsInt($item->priceCents());
    }

    public function test_negative_prices_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BaseMenuItem('Hambúrguer', -1);
    }

    public function test_extra_with_negative_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Extra('bacon', 'Bacon', -400, ['burger']);
    }
}
