<?php

use App\Http\Controllers\CreditNoteDocumentController;
use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\InvoiceDocumentController;
use App\Http\Controllers\Storefront\PreferencesController;
use App\Http\Controllers\Storefront\SearchSuggestController;
use App\Http\Controllers\Support\TicketAttachmentDownloadController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\SyncStaffPermissions;
use App\Livewire\Admin\Appearance\Customize as AppearanceCustomize;
use App\Livewire\Admin\Appearance\ThemesIndex as AppearanceThemes;
use App\Livewire\Admin\Audit\Index as AuditIndex;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Content\NavigationIndex as ContentNavigation;
use App\Livewire\Admin\Content\PagesIndex as ContentPages;
use App\Livewire\Admin\CreditNotes\Create as CreditNotesCreate;
use App\Livewire\Admin\CreditNotes\Show as CreditNotesShow;
use App\Livewire\Admin\Currencies\Index as CurrenciesIndex;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Customers\Properties as CustomerProperties;
use App\Livewire\Admin\Customers\Show as CustomersShow;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Discounts\Index as DiscountsIndex;
use App\Livewire\Admin\Extensions\Index as ExtensionsIndex;
use App\Livewire\Admin\Invoices\Index as InvoicesIndex;
use App\Livewire\Admin\Invoices\Show as InvoicesShow;
use App\Livewire\Admin\Modules\Index as ModulesIndex;
use App\Livewire\Admin\Notifications\EmailLogIndex as NotificationsEmailLog;
use App\Livewire\Admin\Notifications\Templates as NotificationTemplates;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrdersShow;
use App\Livewire\Admin\PlanChanges\Index as PlanChangesIndex;
use App\Livewire\Admin\Products\Create as ProductsCreate;
use App\Livewire\Admin\Products\Edit as ProductsEdit;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Security\TwoFactor as AdminTwoFactor;
use App\Livewire\Admin\Settings\EditGroup as SettingsEditGroup;
use App\Livewire\Admin\Settings\Hub as SettingsHub;
use App\Livewire\Admin\Store\Presets as StorePresets;
use App\Livewire\Admin\System\ApiTokens as SystemApiTokens;
use App\Livewire\Admin\System\FailedJobs as SystemFailedJobs;
use App\Livewire\Admin\System\Updates as SystemUpdates;
use App\Livewire\Admin\Taxes\Index as TaxesIndex;
use App\Livewire\Admin\Tickets\Index as TicketsIndex;
use App\Livewire\Admin\Tickets\Show as TicketsShow;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Customer\Account\Addresses as CustomerAddresses;
use App\Livewire\Customer\Account\CreditNoteShow as CustomerCreditNoteShow;
use App\Livewire\Customer\Account\Credits as CustomerCredits;
use App\Livewire\Customer\Account\Dashboard as CustomerDashboard;
use App\Livewire\Customer\Account\InvoiceShow as CustomerInvoiceShow;
use App\Livewire\Customer\Account\InvoicesIndex as CustomerInvoicesIndex;
use App\Livewire\Customer\Account\OrderShow as CustomerOrderShow;
use App\Livewire\Customer\Account\OrdersIndex as CustomerOrdersIndex;
use App\Livewire\Customer\Account\Profile as CustomerProfile;
use App\Livewire\Customer\Account\TicketCreate as CustomerTicketCreate;
use App\Livewire\Customer\Account\TicketShow as CustomerTicketShow;
use App\Livewire\Customer\Account\TicketsIndex as CustomerTicketsIndex;
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
use App\Livewire\Storefront\PaymentStatusPage;
use App\Livewire\Storefront\ProductShow;
use Illuminate\Support\Facades\Route;

Route::middleware(RedirectIfInstalled::class)->group(function (): void {
    Route::get('/install', InstallerWizard::class)->name('install');
});

Route::get('/', CatalogIndex::class)->name('storefront.home');
Route::post('/preferences/locale', [PreferencesController::class, 'locale'])->name('storefront.preferences.locale');
Route::post('/preferences/currency', [PreferencesController::class, 'currency'])->name('storefront.preferences.currency');
Route::get('/search/suggest', SearchSuggestController::class)
    ->name('storefront.search.suggest');
Route::get('/products/{slug}', ProductShow::class)->name('storefront.product');
Route::get('/categories', StorefrontCategoriesIndex::class)->name('storefront.categories');
Route::get('/categories/{slug}', CategoryShow::class)->name('storefront.category');
Route::get('/cart', CartPage::class)->name('storefront.cart');
Route::get('/checkout', CheckoutPage::class)->name('storefront.checkout');
Route::get('/orders/{order}/confirmation', OrderConfirmation::class)->name('storefront.order.confirmation');
Route::get('/orders/{order}/payment', PaymentStatusPage::class)->name('storefront.payment.status');

Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', CustomerLogin::class)->name('login');
    Route::get('/login/two-factor', TwoFactorChallenge::class)->name('two-factor.challenge');
    Route::get('/register', CustomerRegister::class)->name('register');
    Route::get('/forgot-password', CustomerForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', CustomerResetPassword::class)->name('password.reset');

    Route::redirect('/account/login', '/login')->name('customer.login');
    Route::redirect('/account/register', '/register')->name('customer.register');
    Route::redirect('/account/forgot-password', '/forgot-password')->name('customer.password.request');
    Route::get('/account/reset-password/{token}', function (string $token) {
        return redirect()->route('password.reset', ['token' => $token, 'email' => request('email')]);
    })->name('customer.password.reset');
    Route::redirect('/admin/login', '/login')->name('admin.login');
});

Route::middleware('auth')->prefix('account')->name('customer.')->group(function (): void {
    Route::get('/logout', CustomerLogout::class)->name('logout');
    Route::get('/verify-email', CustomerVerifyEmail::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('customer.verified')->group(function (): void {
        Route::get('/', CustomerDashboard::class)->name('account');
        Route::get('/orders', CustomerOrdersIndex::class)->name('orders.index');
        Route::get('/orders/{order}', CustomerOrderShow::class)->name('orders.show');
        Route::get('/invoices', CustomerInvoicesIndex::class)->name('invoices.index');
        Route::get('/invoices/{invoice}', CustomerInvoiceShow::class)->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoiceDocumentController::class, 'print'])->name('invoices.print');
        Route::get('/invoices/{invoice}/pdf', [InvoiceDocumentController::class, 'pdf'])->name('invoices.pdf');
        Route::get('/credit-notes/{creditNote}', CustomerCreditNoteShow::class)->name('credit-notes.show');
        Route::get('/credit-notes/{creditNote}/print', [CreditNoteDocumentController::class, 'print'])->name('credit-notes.print');
        Route::get('/credit-notes/{creditNote}/pdf', [CreditNoteDocumentController::class, 'pdf'])->name('credit-notes.pdf');
        Route::get('/addresses', CustomerAddresses::class)->name('addresses');
        Route::get('/profile', CustomerProfile::class)->name('profile');
        Route::get('/tickets', CustomerTicketsIndex::class)->name('tickets.index');
        Route::get('/tickets/create', CustomerTicketCreate::class)->name('tickets.create');
        Route::get('/tickets/{ticket}', CustomerTicketShow::class)->name('tickets.show');
        Route::get('/ticket-attachments/{attachment}', TicketAttachmentDownloadController::class)->name('ticket-attachments.download');
        Route::get('/credits', CustomerCredits::class)->name('credits');
    });
});

Route::middleware(['auth', SyncStaffPermissions::class, 'admin.access', 'admin.2fa'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/products', ProductsIndex::class)->name('products.index');
    Route::get('/products/create', ProductsCreate::class)->name('products.create');
    Route::get('/products/{product}/edit', ProductsEdit::class)->name('products.edit');
    Route::get('/categories', CategoriesIndex::class)->name('categories.index');
    Route::get('/currencies', CurrenciesIndex::class)->name('currencies.index');
    Route::get('/users', UsersIndex::class)->name('users.index');
    Route::get('/roles', RolesIndex::class)->name('roles.index');
    Route::get('/security', AdminTwoFactor::class)->name('security.two-factor');
    Route::redirect('/staff', '/admin/users')->name('staff.index');
    Route::get('/orders', OrdersIndex::class)->name('orders.index');
    Route::get('/orders/{order}', OrdersShow::class)->name('orders.show');
    Route::get('/invoices', InvoicesIndex::class)->name('invoices.index');
    Route::get('/invoices/{invoice}', InvoicesShow::class)->name('invoices.show');
    Route::get('/invoices/{invoice}/credit', CreditNotesCreate::class)->name('invoices.credit');
    Route::get('/invoices/{invoice}/print', [InvoiceDocumentController::class, 'print'])->name('invoices.print');
    Route::get('/invoices/{invoice}/pdf', [InvoiceDocumentController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/credit-notes/{creditNote}', CreditNotesShow::class)->name('credit-notes.show');
    Route::get('/credit-notes/{creditNote}/print', [CreditNoteDocumentController::class, 'print'])->name('credit-notes.print');
    Route::get('/credit-notes/{creditNote}/pdf', [CreditNoteDocumentController::class, 'pdf'])->name('credit-notes.pdf');
    Route::get('/discounts', DiscountsIndex::class)->name('discounts.index');
    Route::get('/taxes', TaxesIndex::class)->name('taxes.index');
    Route::get('/plan-changes', PlanChangesIndex::class)->name('plan-changes.index');
    Route::get('/customers', CustomersIndex::class)->name('customers.index');
    Route::get('/customers/properties', CustomerProperties::class)->name('customers.properties');
    Route::get('/customers/{customer}', CustomersShow::class)->name('customers.show');
    Route::get('/tickets', TicketsIndex::class)->name('tickets.index');
    Route::get('/tickets/{ticket}', TicketsShow::class)->name('tickets.show');
    Route::get('/ticket-attachments/{attachment}', TicketAttachmentDownloadController::class)->name('ticket-attachments.download');
    Route::get('/audit', AuditIndex::class)->name('audit.index');
    Route::get('/email-log', NotificationsEmailLog::class)->name('email-log');
    Route::get('/failed-jobs', SystemFailedJobs::class)->name('failed-jobs');
    Route::get('/updates', SystemUpdates::class)->name('updates');
    Route::get('/api-tokens', SystemApiTokens::class)->name('api-tokens');
    Route::get('/notifications', NotificationTemplates::class)->name('notifications');
    Route::get('/modules', ModulesIndex::class)->name('modules.index');
    Route::get('/store-presets', StorePresets::class)->name('store-presets');
    Route::get('/extensions', ExtensionsIndex::class)->name('extensions.index');
    Route::get('/settings', SettingsHub::class)->name('settings.index');
    Route::get('/settings/{group}', SettingsEditGroup::class)->name('settings.edit');
    Route::get('/appearance/themes', AppearanceThemes::class)->name('appearance.themes');
    Route::get('/appearance/customize', AppearanceCustomize::class)->name('appearance.customize');
    Route::get('/appearance/pages', ContentPages::class)->name('appearance.pages');
    Route::get('/appearance/navigation', ContentNavigation::class)->name('appearance.navigation');
});

Route::get('/{slug}', ContentPage::class)
    ->where('slug', '^(?!admin$|install$|cart$|checkout$|products$|categories$|orders$|account$|login$|register$)[A-Za-z0-9\-]+$')
    ->name('storefront.page');
