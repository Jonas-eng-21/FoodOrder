<?php

/*
 * Single source of truth for the order extras (RN02): code, name, price in
 * cents and the product codes that accept each extra. Fries accept none.
 */
return [
    'extras' => [
        'bacon' => ['name' => 'Bacon', 'price_cents' => 400, 'products' => ['burger', 'hotdog']],
        'cheese' => ['name' => 'Queijo extra', 'price_cents' => 300, 'products' => ['burger', 'hotdog']],
        'egg' => ['name' => 'Ovo', 'price_cents' => 200, 'products' => ['burger', 'hotdog']],
    ],
];
