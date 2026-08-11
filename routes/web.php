<?php

use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrdersShow;
use App\Livewire\Admin\Products\Create as ProductsCreate;
use App\Livewire\Admin\Products\Edit as ProductsEdit;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Livewire\Admin\Settings\EditGroup as SettingsEditGroup;
use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\CatalogIndex;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\OrderConfirmation;
use App\Livewire\Storefront\ProductShow;
use Illuminate\Support\Facades\Route;

Route::view('/install', 'installer.welcome')->name('install.welcome');

Route::get('/', CatalogIndex::class)->name('storefront.home');
Route::get('/products/{slug}', ProductShow::class)->name('storefront.product');
Route::get('/cart', CartPage::class)->name('storefront.cart');
Route::get('/checkout', CheckoutPage::class)->name('storefront.checkout');
Route::get('/orders/{order}/confirmation', OrderConfirmation::class)->name('storefront.order.confirmation');

Route::middleware('guest:staff')->group(function (): void {
    Route::get('/admin/login', Login::class)->name('admin.login');
});

Route::middleware('auth:staff')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/products', ProductsIndex::class)->name('products.index');
    Route::get('/products/create', ProductsCreate::class)->name('products.create');
    Route::get('/products/{product}/edit', ProductsEdit::class)->name('products.edit');
    Route::get('/categories', CategoriesIndex::class)->name('categories.index');
    Route::get('/orders', OrdersIndex::class)->name('orders.index');
    Route::get('/orders/{order}', OrdersShow::class)->name('orders.show');
    Route::get('/settings/{group}', SettingsEditGroup::class)->name('settings.edit');
});
