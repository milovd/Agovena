<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\RecordManualPayment;
use App\Livewire\Admin\CreditNotes\Create as AdminCreditNoteCreate;
use App\Livewire\Admin\Extensions\Index as AdminExtensionsIndex;
use App\Livewire\Admin\System\ApiTokens as AdminApiTokens;
use App\Models\Customer;
use App\Models\ExtensionSetting;
use App\Models\Invoice;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * Sensitive-action recent-password matrix (v0.1):
 *
 * REQUIRES recent password:
 * - refund, invoice void, unpaid-order cancel, record manual payment
 * - credit note issue
 * - service terminate
 * - package purge
 * - create/update users, roles/permissions delete
 * - disable/regenerate 2FA
 * - extension secret credential saves (Mollie/Stripe/PostNL/Pterodactyl keys)
 * - admin API token create/revoke
 *
 * Does NOT require recent password:
 * - ordinary product/CMS/theme customize edits
 * - module/extension enable/disable without secret mutation
 * - non-secret extension settings
 * - customer self-service API tokens (account holder already authenticated)
 */
test('issuing a credit note from admin requires a recent password', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['price_amount' => 2000]);
    app(CartService::class)->add($product->id, 1);
    $customer = Customer::factory()->create();
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);
    app(RecordManualPayment::class)->handle($order, $staff, 'CN-PW');
    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    Livewire::actingAs($staff)
        ->test(AdminCreditNoteCreate::class, ['invoice' => $invoice])
        ->set('reason', 'Customer return')
        ->call('startConfirm')
        ->call('issue')
        ->assertSet('showingPasswordConfirmation', true);

    expect($invoice->fresh()->remainingCreditable())->toBe($invoice->total_amount);

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(AdminCreditNoteCreate::class, ['invoice' => $invoice])
        ->set('reason', 'Customer return')
        ->call('startConfirm')
        ->call('issue')
        ->assertHasNoErrors()
        ->assertRedirect();
});

test('saving extension secret credentials requires a recent password', function () {
    $staff = $this->createStaff();
    installAndEnableExtension('mollie');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('mollie', 'api_key');
    // Ensure env override cannot make isConfigured() true during this gate test.
    putenv('AGOVENA_EXT_MOLLIE_API_KEY');
    unset($_ENV['AGOVENA_EXT_MOLLIE_API_KEY'], $_SERVER['AGOVENA_EXT_MOLLIE_API_KEY']);

    Livewire::actingAs($staff)
        ->test(AdminExtensionsIndex::class)
        ->call('openSettings', 'mollie')
        ->set('settingsForm.api_key', 'test_dummy_key_for_password_gate')
        ->call('saveSettings')
        ->assertSet('showingPasswordConfirmation', true);

    expect(
        ExtensionSetting::query()
            ->where('extension_id', 'mollie')
            ->where('key', 'api_key')
            ->exists()
    )->toBeFalse();

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(AdminExtensionsIndex::class)
        ->call('openSettings', 'mollie')
        ->set('settingsForm.api_key', 'test_dummy_key_for_password_gate')
        ->call('saveSettings')
        ->assertSet('showingPasswordConfirmation', false);

    expect(
        ExtensionSetting::query()
            ->where('extension_id', 'mollie')
            ->where('key', 'api_key')
            ->exists()
    )->toBeTrue();
});

test('admin API token create requires a recent password', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(AdminApiTokens::class)
        ->set('token_name', 'ops')
        ->call('createToken')
        ->assertSet('showingPasswordConfirmation', true)
        ->assertSet('plainTextToken', null);

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(AdminApiTokens::class)
        ->set('token_name', 'ops')
        ->call('createToken')
        ->assertSet('showingPasswordConfirmation', false);

    expect($staff->fresh()->tokens()->count())->toBe(1);
});
