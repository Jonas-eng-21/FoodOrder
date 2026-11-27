<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __invoke(): View
    {
        return view('menu.index', [
            'products' => Product::active()->orderBy('id')->get(),
        ]);
    }
}
