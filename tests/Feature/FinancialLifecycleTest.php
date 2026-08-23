<?php

declare(strict_types=1);

use Agovena\Modules\Digital\Models\DigitalAsset;
use Agovena\Modules\Digital\Models\DigitalEntitlement;
use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Inventory\Models\InventoryStock;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\Shipment;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use Agovena\Modules\Subscriptions\Models\Subscription;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Invoices\IssueCreditNote;
use App\Agovena\Invoices\VoidInvoice;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\CreditNotes\Create as AdminCreditNoteCreate;
use App\Livewire\Admin\Invoices\Show as AdminInvoiceShow;
use App\Livewire\Customer\Account\CreditNoteShow;
use App\Livewire\Customer\Account\InvoiceShow;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function placePaidCustomerOrder(int $priceAmount = 4000, int $qty = 2): array
{
    $cart = app(CartService::class);
    $cart->clear();
    $product = Product::factory()->active()->create(['price_amount' => $priceAmount]);
    $cart->add($product->id, $qty);

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

    $staff = test()->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'PAY-FIN');

    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    return [$order->fresh(['items', 'payment', 'invoice']), $invoice->fresh('items'), $customer, $staff, $product];
}

test('partial credit note and partial refund leave the issued invoice unchanged', function () {
    [$order, $invoice, $customer, $staff] = placePaidCustomerOrder();
    $originalTotal = $invoice->total_amount;
    $originalItemTotal = $invoice->items->first()->line_total_amount;
    $productItem = $invoice->creditableItems()->first();

    $creditNote = app(IssueCreditNote::class)->handle(
        $invoice,
        $staff,
        'Damaged unit',
        [$productItem->id => 1],
    );

    $invoice->refresh();

    expect($creditNote->number)->toStartWith('CN-')
        ->and($creditNote->total_amount)->toBe($productItem->unit_amount)
        ->and($invoice->total_amount)->toBe($originalTotal)
        ->and($invoice->items->first()->line_total_amount)->toBe($originalItemTotal)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->remainingCreditable())->toBe($originalTotal - $creditNote->total_amount);

    $refund = app(RecordRefund::class)->handle(
        $order->payment,
        $staff,
        $creditNote->total_amount,
        'Partial refund for damaged unit',
        $creditNote->id,
    );

    expect($refund->status->value)->toBe('completed')
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($invoice->fresh()->total_amount)->toBe($originalTotal);

    expect(AuditLog::query()->where('action', 'credit_note.issued')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'refund.completed')->exists())->toBeTrue();

    Livewire::actingAs($customer->user)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee($creditNote->number);

    Livewire::actingAs($customer->user)
        ->test(CreditNoteShow::class, ['creditNote' => $creditNote])
        ->assertOk()
        ->assertSee($creditNote->number)
        ->assertSee(__('customer.account.print_credit_note'));

    test()->actingAs($customer->user)
        ->get(route('customer.credit-notes.pdf', $creditNote))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('full credit and full refund mark the payment refunded', function () {
    [$order, $invoice, $customer, $staff] = placePaidCustomerOrder();

    $creditNote = app(IssueCreditNote::class)->handle($invoice, $staff, 'Full cancellation');
    $refund = app(RecordRefund::class)->handle(
        $order->payment->fresh(),
        $staff,
        $order->payment->amount,
        'Full refund',
        $creditNote->id,
    );

    expect($creditNote->total_amount)->toBe($invoice->total_amount)
        ->and($invoice->fresh()->remainingCreditable())->toBe(0)
        ->and($refund->amount)->toBe($order->payment->amount)
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->total_amount)->toBe($creditNote->total_amount);
});

test('multiple partial credits cannot exceed the original invoice', function () {
    [$order, $invoice, $customer, $staff] = placePaidCustomerOrder();
    $productItem = $invoice->creditableItems()->first();

    app(IssueCreditNote::class)->handle($invoice, $staff, 'First unit', [$productItem->id => 1]);

    expect(fn () => app(IssueCreditNote::class)->handle(
        $invoice->fresh('items'),
        $staff,
        'Too much',
        [$productItem->id => 2],
    ))->toThrow(ValidationException::class);

    app(IssueCreditNote::class)->handle($invoice->fresh('items'), $staff, 'Second unit', [$productItem->id => 1]);

    expect($invoice->fresh()->remainingCreditable())->toBe(0)
        ->and(fn () => app(IssueCreditNote::class)->handle($invoice->fresh('items'), $staff, 'Over'))
        ->toThrow(ValidationException::class);
});

test('unpaid invoice can be voided and cannot be paid afterward', function () {
    $product = Product::factory()->active()->create(['price_amount' => 1500]);
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

    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();
    $staff = test()->createStaff();

    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->canVoid())->toBeTrue();

    $voided = app(VoidInvoice::class)->handle($invoice, $staff);

    expect($voided->status)->toBe(InvoiceStatus::Void)
        ->and($voided->total_amount)->toBe($invoice->total_amount)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->isAwaitingPayment())->toBeFalse()
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'invoice.voided')->exists())->toBeTrue();

    expect(fn () => app(RecordManualPayment::class)->handle($order->fresh(), $staff, 'TOO-LATE'))
        ->toThrow(ValidationException::class);

    expect(fn () => $voided->delete())
        ->toThrow(RuntimeException::class);
});

test('refund does not silently destroy mixed module fulfillment state', function () {
    installAndEnableModules(['inventory', 'shipping', 'digital', 'subscriptions', 'provisioning']);
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $capabilities = app(ProductCapabilityManager::class);

    $physical = Product::factory()->active()->create(['name' => 'Lamp', 'price_amount' => 2500]);
    $capabilities->enable($physical, 'physical');
    $capabilities->enable($physical, 'inventory');
    $capabilities->enable($physical, 'shippable', ['weight_grams' => 800]);
    app(InventoryService::class)->setQuantity($physical, 4);

    $digital = Product::factory()->active()->create(['name' => 'Guide', 'price_amount' => 1200]);
    $capabilities->enable($digital, 'digital');
    Storage::fake('local');
    $path = 'digital/'.$digital->id.'/guide.txt';
    Storage::disk('local')->put($path, 'guide');
    DigitalAsset::query()->create([
        'product_id' => $digital->id,
        'label' => 'Guide',
        'disk' => 'local',
        'path' => $path,
        'filename' => 'guide.txt',
        'download_limit' => 3,
        'is_active' => true,
    ]);

    $hosted = Product::factory()->active()->create(['name' => 'VPS', 'price_amount' => 4000]);
    $capabilities->enable($hosted, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    $capabilities->enable($hosted, 'provisionable', ['provider_key' => 'manual']);

    $method = ShippingMethod::query()->create([
        'name' => 'Parcel',
        'code' => 'fin-parcel',
        'type' => ShippingMethodType::Flat,
        'zone_id' => null,
        'config' => ['amount' => 500],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 10,
    ]);

    $cart = app(CartService::class);
    $cart->add($physical->id, 1);
    $cart->add($digital->id, 1);
    $cart->add($hosted->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Keizersgracht 1',
            'city' => 'Amsterdam',
            'postal_code' => '1015 CN',
            'country' => 'NL',
        ]),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    $staff = test()->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'MIXED-REFUND');

    expect(DigitalEntitlement::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(Subscription::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(ServiceInstance::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(Shipment::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(InventoryStock::query()->where('product_id', $physical->id)->value('quantity'))->toBe(3);

    app(RecordRefund::class)->handle(
        $order->payment->fresh(),
        $staff,
        500,
        'Goodwill refund',
    );

    expect(DigitalEntitlement::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(Subscription::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(ServiceInstance::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(Shipment::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(InventoryStock::query()->where('product_id', $physical->id)->value('quantity'))->toBe(3)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded);
});

test('staff without credit or refund permission is denied', function () {
    [$order, $invoice] = placePaidCustomerOrder();
    $viewer = test()->createStaff([], ['invoices.view', 'orders.view', 'dashboard.view']);

    Livewire::actingAs($viewer)
        ->test(AdminCreditNoteCreate::class, ['invoice' => $invoice])
        ->assertForbidden();

    Livewire::actingAs($viewer)
        ->test(AdminInvoiceShow::class, ['invoice' => $invoice])
        ->assertOk()
        ->call('startVoid')
        ->assertForbidden();

    Livewire::actingAs($viewer)
        ->test(AdminInvoiceShow::class, ['invoice' => $invoice])
        ->call('startRefund')
        ->assertForbidden();

    expect(fn () => app(IssueCreditNote::class)->handle($invoice, $viewer, 'No'))
        ->toThrow(HttpException::class);

    expect(fn () => app(RecordRefund::class)->handle($order->payment, $viewer, 100, 'No'))
        ->toThrow(HttpException::class);
});
