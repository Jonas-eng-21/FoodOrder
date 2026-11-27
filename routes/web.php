<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderLifecycleController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', MenuController::class)->name('menu.index');

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/pay', [PaymentController::class, 'store'])->name('orders.pay');
Route::post('/orders/{order}/start', [OrderLifecycleController::class, 'start'])->name('orders.start');
Route::post('/orders/{order}/ready', [OrderLifecycleController::class, 'ready'])->name('orders.ready');
Route::post('/orders/{order}/deliver', [OrderLifecycleController::class, 'deliver'])->name('orders.deliver');
Route::post('/orders/{order}/cancel', [OrderLifecycleController::class, 'cancel'])->name('orders.cancel');
