<?php

declare(strict_types=1);

use App\Agovena\Api\OpenApiDocument;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('/openapi.json', fn (OpenApiDocument $document) => response()->json($document->toArray()))
        ->name('openapi');

    Route::middleware('throttle:api-catalog')->group(function (): void {
        Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
        Route::get('/products/{slug}', [CatalogController::class, 'product'])->name('products.show');
        Route::get('/categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::get('/categories/{slug}', [CatalogController::class, 'category'])->name('categories.show');
        Route::get('/search', [CatalogController::class, 'search'])->name('search');
    });

    Route::middleware('throttle:api-auth')->group(function (): void {
        Route::post('/auth/tokens', [TokenController::class, 'store'])->name('auth.tokens.store');
    });

    Route::middleware('throttle:api')->group(function (): void {
        Route::get('/cart', [AccountController::class, 'cart'])->name('cart.show');
        Route::post('/cart', [AccountController::class, 'addToCart'])->name('cart.add');
        Route::patch('/cart/{lineKey}', [AccountController::class, 'updateCartLine'])->name('cart.update');
        Route::delete('/cart/{lineKey}', [AccountController::class, 'removeCartLine'])->name('cart.remove');
        Route::get('/checkout/requirements', [CheckoutController::class, 'requirements'])->name('checkout.requirements');
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/me', [TokenController::class, 'me'])->name('me.show');
        Route::patch('/me', [TokenController::class, 'updateMe'])->name('me.update');
        Route::delete('/auth/tokens', [TokenController::class, 'destroy'])->name('auth.tokens.destroy');

        Route::get('/addresses', [AccountController::class, 'addresses'])->name('addresses.index');
        Route::post('/addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::patch('/addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');

        Route::get('/orders', [CommerceController::class, 'orders'])->name('orders.index');
        Route::get('/orders/{order}', [CommerceController::class, 'order'])->name('orders.show');
        Route::get('/invoices', [CommerceController::class, 'invoices'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [CommerceController::class, 'invoice'])->name('invoices.show');
        Route::get('/credit-notes', [CommerceController::class, 'creditNotes'])->name('credit-notes.index');
        Route::get('/credit-notes/{creditNote}', [CommerceController::class, 'creditNote'])->name('credit-notes.show');
        Route::get('/support-tickets', [CommerceController::class, 'tickets'])->name('support-tickets.index');
        Route::get('/support-tickets/{ticket}', [CommerceController::class, 'ticket'])->name('support-tickets.show');
    });

    Route::middleware(['auth:sanctum', 'throttle:api-sensitive'])->group(function (): void {
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::post('/orders/{order}/pay', [CheckoutController::class, 'pay'])->name('orders.pay');
    });
});
