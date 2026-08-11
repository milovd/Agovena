<?php

use App\Http\Controllers\Storefront\SearchSuggestController;
use App\Livewire\Admin\Appearance\Customize as AppearanceCustomize;
use App\Livewire\Admin\Appearance\ThemesIndex as AppearanceThemes;
use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Content\NavigationIndex as ContentNavigation;
use App\Livewire\Admin\Content\PagesIndex as ContentPages;
use App\Livewire\Admin\Currencies\Index as CurrenciesIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrdersShow;
use App\Livewire\Admin\Products\Create as ProductsCreate;
use App\Livewire\Admin\Products\Edit as ProductsEdit;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Livewire\Admin\Settings\EditGroup as SettingsEditGroup;
use App\Livewire\Admin\Settings\Hub as SettingsHub;
use App\Livewire\Admin\Staff\Index as StaffIndex;
use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\CatalogIndex;
use App\Livewire\Storefront\CategoryShow;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\ContentPage;
use App\Livewire\Storefront\OrderConfirmation;
use App\Livewire\Storefront\ProductShow;
use Illuminate\Support\Facades\Route;

Route::view('/install', 'installer.welcome')->name('install.welcome');

Route::get('/', CatalogIndex::class)->name('storefront.home');
Route::get('/search/suggest', SearchSuggestController::class)
    ->name('storefront.search.suggest');
Route::get('/products/{slug}', ProductShow::class)->name('storefront.product');
Route::get('/categories/{slug}', CategoryShow::class)->name('storefront.category');
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
    Route::get('/currencies', CurrenciesIndex::class)->name('currencies.index');
    Route::get('/staff', StaffIndex::class)->name('staff.index');
    Route::get('/orders', OrdersIndex::class)->name('orders.index');
    Route::get('/orders/{order}', OrdersShow::class)->name('orders.show');
    Route::get('/settings', SettingsHub::class)->name('settings.index');
    Route::get('/settings/{group}', SettingsEditGroup::class)->name('settings.edit');
    Route::get('/appearance/themes', AppearanceThemes::class)->name('appearance.themes');
    Route::get('/appearance/customize', AppearanceCustomize::class)->name('appearance.customize');
    Route::get('/appearance/pages', ContentPages::class)->name('appearance.pages');
    Route::get('/appearance/navigation', ContentNavigation::class)->name('appearance.navigation');
});

Route::get('/{slug}', ContentPage::class)
    ->where('slug', '^(?!admin$|install$|cart$|checkout$|products$|categories$|orders$)[A-Za-z0-9\-]+$')
    ->name('storefront.page');
