<?php

declare(strict_types=1);

use App\Agovena\Orders\OrderListStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Models\Order;
use App\Models\Payment;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function orderWithPayment(string $number, OrderStatus $status, ?PaymentStatus $payment): Order
{
    $order = Order::factory()->create(['number' => $number, 'status' => $status]);
    if ($payment !== null) {
        Payment::query()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'method' => 'manual',
            'status' => $payment,
        ]);
    }

    return $order->fresh(['payment']);
}

/** @return array<string, array{OrderStatus, ?PaymentStatus, OrderListStatus}> */
function orderListStatusMatrix(): array
{
    return [
        'pending without payment' => [OrderStatus::Pending, null, OrderListStatus::AwaitingPayment],
        'pending payment' => [OrderStatus::Pending, PaymentStatus::Pending, OrderListStatus::AwaitingPayment],
        'pending with cancelled attempt' => [OrderStatus::Pending, PaymentStatus::Cancelled, OrderListStatus::AwaitingPayment],
        'pending with failed payment' => [OrderStatus::Pending, PaymentStatus::Failed, OrderListStatus::PaymentFailed],
        'pending with expired payment' => [OrderStatus::Pending, PaymentStatus::Expired, OrderListStatus::PaymentExpired],
        'paid' => [OrderStatus::Paid, PaymentStatus::Paid, OrderListStatus::Paid],
        'paid without payment record' => [OrderStatus::Paid, null, OrderListStatus::Paid],
        'partially refunded' => [OrderStatus::Paid, PaymentStatus::PartiallyRefunded, OrderListStatus::PartiallyRefunded],
        'refunded' => [OrderStatus::Paid, PaymentStatus::Refunded, OrderListStatus::Refunded],
        'cancelled with failed payment' => [OrderStatus::Cancelled, PaymentStatus::Failed, OrderListStatus::Cancelled],
        'cancelled without payment' => [OrderStatus::Cancelled, null, OrderListStatus::Cancelled],
    ];
}

it('derives one list status from the order and payment status without changing either', function (): void {
    foreach (orderListStatusMatrix() as $label => [$orderStatus, $paymentStatus, $expected]) {
        $order = orderWithPayment('AG-'.md5($label), $orderStatus, $paymentStatus);

        expect(OrderListStatus::for($order))->toBe($expected, $label)
            ->and($order->fresh()->status)->toBe($orderStatus)
            ->and($order->fresh()->payment?->status)->toBe($paymentStatus);
    }
});

it('filters every list status to exactly the orders that display it', function (): void {
    $expectedByStatus = [];
    foreach (orderListStatusMatrix() as $label => [$orderStatus, $paymentStatus, $expected]) {
        $order = orderWithPayment('AG-'.substr(md5($label), 0, 10), $orderStatus, $paymentStatus);
        $expectedByStatus[$expected->value][] = $order->id;
    }

    foreach (OrderListStatus::cases() as $status) {
        $ids = OrderListStatus::apply(Order::query(), $status)->pluck('id')->sort()->values()->all();
        $expectedIds = collect($expectedByStatus[$status->value] ?? [])->sort()->values()->all();

        expect($ids)->toBe($expectedIds, $status->value);
    }
});

it('shows one status column and one combined status filter on the order list', function (): void {
    $staff = $this->createStaff([], ['orders.view']);
    orderWithPayment('AG-REFUNDED-1', OrderStatus::Paid, PaymentStatus::Refunded);

    $html = Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->assertSee('wire:model.live="status"', false)
        ->assertDontSee('paymentStatus', false)
        ->assertDontSee('id="payment-status"', false)
        ->assertSee(__('admin.orders.list_status.refunded'))
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $headers = collect(iterator_to_array($xpath->query('//table[contains(@class, "ag-table--orders")]/thead/tr/th')))
        ->map(fn (DOMElement $th): string => trim($th->textContent))
        ->all();

    // Order, customer, total, one status, created, actions: no separate payment column.
    expect($headers)->toHaveCount(6)
        ->and($headers[3])->toBe(__('common.status'))
        ->and($xpath->query('//select[@id="order-status"]/option'))->toHaveCount(count(OrderListStatus::cases()) + 1)
        ->and($xpath->query('//select'))->toHaveCount(1);
});

it('filters the list by the combined status and ignores unknown values', function (): void {
    $staff = $this->createStaff([], ['orders.view']);
    orderWithPayment('AG-FAILED-1', OrderStatus::Pending, PaymentStatus::Failed);
    orderWithPayment('AG-PAID-1', OrderStatus::Paid, PaymentStatus::Paid);

    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->set('status', OrderListStatus::PaymentFailed->value)
        ->assertSee('AG-FAILED-1')
        ->assertDontSee('AG-PAID-1')
        ->set('status', 'paid')
        ->assertSee('AG-PAID-1')
        ->assertDontSee('AG-FAILED-1')
        ->set('status', 'not-a-status')
        ->assertSet('status', '')
        ->assertSee('AG-PAID-1')
        ->assertSee('AG-FAILED-1');
});

it('labels every combined status in English and Dutch', function (): void {
    foreach (OrderListStatus::cases() as $status) {
        foreach (['en', 'nl'] as $locale) {
            $key = 'admin.orders.list_status.'.$status->value;
            expect(__($key, [], $locale))->not->toBe($key, "{$locale} {$status->value}");
        }
    }
});
