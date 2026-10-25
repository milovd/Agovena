<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Orders\DeleteSelectedOrders;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function confirmRecentPasswordFor(User $staff): void
{
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);
}

it('renders accessible bulk selection and a confirmed delete action only for staff who can delete orders', function (): void {
    $order = Order::factory()->create(['number' => 'AG-BULK-1']);
    $deleter = $this->createStaff([], ['orders.view', 'orders.delete']);

    $markup = Livewire::actingAs($deleter)->test(OrdersIndex::class)
        ->assertSee('data-bulk-model="selectedOrderIds"', false)
        ->assertSee('aria-label="'.__('admin.orders.bulk_select_row', ['number' => $order->number]).'"', false)
        ->assertSee('@click="toggleAll"', false)
        ->assertSee('wire:click="bulkDeleteOrders"', false)
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($markup);
    $xpath = new DOMXPath($dom);
    $actions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ag-bulk-selection__floating-actions ")]');
    expect($xpath->query('//table[contains(@class, "ag-table--orders")]/thead/tr/th[1]//button[contains(@class, "ag-bulk-selection__select") and @role="checkbox" and @aria-label]'))->toHaveCount(1)
        ->and($xpath->query('//table[contains(@class, "ag-table--orders")]/thead/tr/th[2]//*[contains(@class, "ag-bulk-selection__count")][@aria-live="polite"]'))->toHaveCount(1)
        ->and($actions)->toHaveCount(1)
        ->and($xpath->query('.//button[@aria-controls="order-bulk-actions" and @aria-label]', $actions->item(0)))->toHaveCount(1)
        ->and($xpath->query('.//*[@id="order-bulk-actions"]//button[@*[name()="wire:confirm"]]', $actions->item(0)))->toHaveCount(1);

    $viewer = $this->createStaff([], ['orders.view', 'orders.update']);
    Livewire::actingAs($viewer)->test(OrdersIndex::class)
        ->assertDontSee('selectedOrderIds', false)
        ->assertDontSee('bulkDeleteOrders', false)
        ->assertDontSee('ag-table__select-col', false);
});

it('toggles select-all for the current order page and clears it when filters or the page change', function (): void {
    $staff = $this->createStaff([], ['orders.view', 'orders.delete']);
    Order::factory()->count(16)->create();

    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->call('selectCurrentPage')
        ->assertCount('selectedOrderIds', 15)
        ->call('selectCurrentPage')
        ->assertSet('selectedOrderIds', [])
        ->call('selectCurrentPage')
        ->set('status', 'awaiting_payment')
        ->assertSet('selectedOrderIds', [])
        ->set('status', '')
        ->call('selectCurrentPage')
        ->set('search', 'x')
        ->assertSet('selectedOrderIds', [])
        ->set('search', '')
        ->call('selectCurrentPage')
        ->call('gotoPage', 2)
        ->assertSet('selectedOrderIds', [])
        ->call('selectCurrentPage')
        ->assertCount('selectedOrderIds', 1);
});

it('requires a recent password before deleting selected orders and keeps the selection for the retry', function (): void {
    $staff = $this->createStaff([], ['orders.view', 'orders.delete']);
    $order = Order::factory()->create();

    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->set('selectedOrderIds', [(string) $order->id])
        ->call('bulkDeleteOrders')
        ->assertSet('showingPasswordConfirmation', true)
        ->assertSet('pendingPasswordAction', 'bulkDeleteOrders')
        ->assertSet('selectedOrderIds', [(string) $order->id])
        ->assertSet('bulkDeleteResult', null);

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('deletes only open orders without financial history and reports kept, skipped and deleted orders', function (): void {
    $staff = $this->createStaff([], ['orders.view', 'orders.delete']);
    $open = Order::factory()->create(['number' => 'AG-OPEN']);
    $openInvoice = app(IssueInvoiceFromOrder::class)->handle($open);
    $paid = Order::factory()->create(['number' => 'AG-PAID', 'status' => OrderStatus::Paid]);
    $paidInvoice = app(IssueInvoiceFromOrder::class)->handle($paid);
    confirmRecentPasswordFor($staff);

    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->set('selectedOrderIds', [(string) $open->id, (string) $paid->id, (string) $open->id, '999999'])
        ->call('bulkDeleteOrders')
        ->assertHasNoErrors()
        ->assertSet('selectedOrderIds', [])
        ->assertSet('bulkDeleteResult', ['deleted' => 1, 'kept' => 1, 'skipped' => 1, 'failed' => 0])
        ->assertSee(__('admin.orders.bulk_delete_result', ['deleted' => 1, 'kept' => 1, 'skipped' => 1, 'failed' => 0]));

    expect(Order::query()->whereKey($open->id)->exists())->toBeFalse()
        ->and(Invoice::query()->whereKey($openInvoice->id)->exists())->toBeFalse()
        ->and(Order::query()->whereKey($paid->id)->exists())->toBeTrue()
        ->and(Invoice::query()->whereKey($paidInvoice->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'order.deleted')->count())->toBe(1);
});

it('ignores forged ids that are not on the filtered current page', function (): void {
    $staff = $this->createStaff([], ['orders.view', 'orders.delete']);
    $visible = Order::factory()->create(['number' => 'AG-MATCH-1']);
    $filteredOut = Order::factory()->create(['number' => 'AG-OTHER-1']);
    confirmRecentPasswordFor($staff);

    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->set('search', 'AG-MATCH')
        ->set('selectedOrderIds', [(string) $visible->id, (string) $filteredOut->id])
        ->call('bulkDeleteOrders')
        ->assertSet('bulkDeleteResult', ['deleted' => 1, 'kept' => 0, 'skipped' => 1, 'failed' => 0]);

    expect(Order::query()->whereKey($visible->id)->exists())->toBeFalse()
        ->and(Order::query()->whereKey($filteredOut->id)->exists())->toBeTrue();
});

it('denies forged bulk deletes, rejects empty and oversized selections and locks the result', function (): void {
    $order = Order::factory()->create();
    $viewer = $this->createStaff([], ['orders.view', 'orders.update']);
    confirmRecentPasswordFor($viewer);
    Livewire::actingAs($viewer)->test(OrdersIndex::class)
        ->set('selectedOrderIds', [(string) $order->id])
        ->call('bulkDeleteOrders')
        ->assertForbidden();

    $staff = $this->createStaff([], ['orders.view', 'orders.delete']);
    confirmRecentPasswordFor($staff);
    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->call('bulkDeleteOrders')
        ->assertHasErrors(['selectedOrderIds']);
    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->set('selectedOrderIds', array_fill(0, 501, (string) $order->id))
        ->call('bulkDeleteOrders')
        ->assertHasErrors(['selectedOrderIds']);

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and(fn () => Livewire::actingAs($staff)->test(OrdersIndex::class)
            ->set('bulkDeleteResult', ['deleted' => 500, 'kept' => 0, 'skipped' => 0, 'failed' => 0]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('checks the delete permission per order in the domain action and caps the selection', function (): void {
    $order = Order::factory()->create();
    $viewer = $this->createStaff([], ['orders.view']);
    expect(app(DeleteSelectedOrders::class)->handle([$order->id], $viewer))
        ->toBe(['deleted' => 0, 'kept' => 0, 'skipped' => 1, 'failed' => 0])
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();

    $staff = $this->createStaff([], ['orders.view', 'orders.delete']);
    expect(fn () => app(DeleteSelectedOrders::class)->handle(array_fill(0, 501, $order->id), $staff))
        ->toThrow(ValidationException::class);
});

it('keeps the order filters on one desktop row', function (): void {
    $css = (string) file_get_contents(resource_path('css/admin/components/_table.css'));

    // The filter group fills the toolbar instead of shrinking to its intrinsic width,
    // where a flex-basis on the search field forced the last select onto a second row.
    expect($css)->toMatch('/\.ag-toolbar__filters \{[^}]*flex: 1 1 auto;[^}]*min-width: 0;/s');
});
