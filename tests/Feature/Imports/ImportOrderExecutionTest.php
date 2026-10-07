<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Models\ImportRow;
use App\Models\Order;
use App\Models\OrderItem;

it('imports an order with mapped customer and product line items', function (): void {
    $customerPath = tempnam(sys_get_temp_dir(), 'agovena-order-customer-');
    file_put_contents($customerPath, "external_id,email,name\nC-1,order@example.test,Order Customer\n");
    $productPath = tempnam(sys_get_temp_dir(), 'agovena-order-product-');
    file_put_contents($productPath, "external_id,name,price_amount\nP-1,Order Product,500\n");

    $registry = app(ImportAdapterRegistry::class);
    $executor = app(ImportExecutor::class);
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv', false);
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv', false);

    $orderPath = tempnam(sys_get_temp_dir(), 'agovena-order-');
    $handle = fopen($orderPath, 'wb');
    fputcsv($handle, ['external_id', 'customer_id', 'total_amount', 'items_json']);
    fputcsv($handle, ['O-1', 'C-1', '1000', json_encode([
        ['product_external_id' => 'P-1', 'quantity' => 2, 'unit_amount' => 500, 'label' => 'Order Product'],
    ], JSON_THROW_ON_ERROR)]);
    fclose($handle);

    $run = $executor->run($orderPath, $registry->for('csv', 'order'), 'csv', false);
    $order = Order::query()->first();

    expect($run->status)->toBe('completed')
        ->and($run->errors)->toBe(0)
        ->and($order)->not->toBeNull()
        ->and($order?->total_amount)->toBe(1000)
        ->and(OrderItem::query()->where('order_id', $order?->id)->count())->toBe(1)
        ->and(ImportRow::query()->where('import_run_id', $run->id)->value('status'))->toBe('imported');

    unlink($customerPath);
    unlink($productPath);
    unlink($orderPath);
});
