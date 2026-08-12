<?php

use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\Storefront\SearchSuggestController;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\SyncStaffPermissions;
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
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Settings\EditGroup as SettingsEditGroup;
use App\Livewire\Admin\Settings\Hub as SettingsHub;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Customer\Account\Dashboard as CustomerDashboard;
use App\Livewire\Customer\Account\OrderShow as CustomerOrderShow;
use App\Livewire\Customer\Account\OrdersIndex as CustomerOrdersIndex;
use App\Livewire\Customer\Account\Profile as CustomerProfile;
use App\Livewire\Customer\Auth\ForgotPassword as CustomerForgotPassword;
use App\Livewire\Customer\Auth\Login as CustomerLogin;
use App\Livewire\Customer\Auth\Logout as CustomerLogout;
use App\Livewire\Customer\Auth\Register as CustomerRegister;
use App\Livewire\Customer\Auth\ResetPassword as CustomerResetPassword;
use App\Livewire\Customer\Auth\VerifyEmail as CustomerVerifyEmail;
use App\Livewire\Installer\Wizard as InstallerWizard;
use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\CatalogIndex;
use App\Livewire\Storefront\CategoriesIndex as StorefrontCategoriesIndex;
use App\Livewire\Storefront\CategoryShow;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\ContentPage;
use App\Livewire\Storefront\OrderConfirmation;
use App\Livewire\Storefront\ProductShow;
use Illuminate\Support\Facades\Route;

Route::middleware(RedirectIfInstalled::class)->group(function (): void {
    Route::get('/install', InstallerWizard::class)->name('install');
});

Route::get('/', CatalogIndex::class)->name('storefront.home');
Route::get('/search/suggest', SearchSuggestController::class)
    ->name('storefront.search.suggest');
Route::get('/products/{slug}', ProductShow::class)->name('storefront.product');
Route::get('/categories', StorefrontCategoriesIndex::class)->name('storefront.categories');
Route::get('/categories/{slug}', CategoryShow::class)->name('storefront.category');
Route::get('/cart', CartPage::class)->name('storefront.cart');
Route::get('/checkout', CheckoutPage::class)->name('storefront.checkout');
Route::get('/orders/{order}/confirmation', OrderConfirmation::class)->name('storefront.order.confirmation');

Route::middleware('guest:customer')->prefix('account')->name('customer.')->group(function (): void {
    Route::get('/login', CustomerLogin::class)->name('login');
    Route::get('/register', CustomerRegister::class)->name('register');
    Route::get('/forgot-password', CustomerForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', CustomerResetPassword::class)->name('password.reset');
});

Route::middleware('auth:customer')->prefix('account')->name('customer.')->group(function (): void {
    Route::get('/logout', CustomerLogout::class)->name('logout');
    Route::get('/verify-email', CustomerVerifyEmail::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('customer.verified')->group(function (): void {
        Route::get('/', CustomerDashboard::class)->name('account');
        Route::get('/orders', CustomerOrdersIndex::class)->name('orders.index');
        Route::get('/orders/{order}', CustomerOrderShow::class)->name('orders.show');
        Route::get('/profile', CustomerProfile::class)->name('profile');
    });
});

Route::middleware('guest:staff')->group(function (): void {
    Route::get('/admin/login', Login::class)->name('admin.login');
});

Route::middleware(['auth:staff', SyncStaffPermissions::class])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/products', ProductsIndex::class)->name('products.index');
    Route::get('/products/create', ProductsCreate::class)->name('products.create');
    Route::get('/products/{product}/edit', ProductsEdit::class)->name('products.edit');
    Route::get('/categories', CategoriesIndex::class)->name('categories.index');
    Route::get('/currencies', CurrenciesIndex::class)->name('currencies.index');
    Route::get('/users', UsersIndex::class)->name('users.index');
    Route::get('/roles', RolesIndex::class)->name('roles.index');
    Route::redirect('/staff', '/admin/users')->name('staff.index');
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
    ->where('slug', '^(?!admin$|install$|cart$|checkout$|products$|categories$|orders$|account$)[A-Za-z0-9\-]+$')
    ->name('storefront.page');
