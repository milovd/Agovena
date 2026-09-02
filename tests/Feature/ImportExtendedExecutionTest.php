<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Agovena\Imports\ImportRollback;
use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductImage;

function writeExtendedImportFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-extended-');
    file_put_contents($path, $contents);

    return $path;
}

/** @param list<string> $headers @param list<string|int> $row */
function writeExtendedCsvFixture(array $headers, array $row): string
{
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $headers);
    fputcsv($handle, $row);
    rewind($handle);
    $contents = stream_get_contents($handle);
    fclose($handle);

    return writeExtendedImportFixture($contents ?: '');
}

it('imports discounts and safe product media mappings and rolls them back', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $productPath = writeExtendedImportFixture("external_id,name,price_amount\nP-MEDIA,Media product,1999\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');

    $discountPath = writeExtendedImportFixture("external_id,code,type,value,currency,max_uses\nD-1,SAVE10,percent,10,,100\n");
    $discountRun = $executor->run($discountPath, $registry->for('csv', 'discount'), 'csv');

    $mediaPath = writeExtendedImportFixture("external_id,product_external_id,path,sort\nM-1,P-MEDIA,products/media-product.jpg,2\n");
    $mediaRun = $executor->run($mediaPath, $registry->for('csv', 'media'), 'csv');

    expect($discountRun->errors)->toBe(0)
        ->and(DiscountCode::query()->where('code', 'SAVE10')->value('max_uses'))->toBe(100)
        ->and($mediaRun->errors)->toBe(0)
        ->and(ProductImage::query()->where('path', 'products/media-product.jpg')->value('sort'))->toBe(2);

    app(ImportRollback::class)->handle($mediaRun);
    app(ImportRollback::class)->handle($discountRun);

    expect(ProductImage::query()->where('path', 'products/media-product.jpg')->exists())->toBeFalse()
        ->and(DiscountCode::query()->where('code', 'SAVE10')->exists())->toBeFalse();

    unlink($productPath);
    unlink($discountPath);
    unlink($mediaPath);
});

it('imports invoices and payment transactions from mapped dependencies', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeExtendedImportFixture("external_id,email,name\nC-INVOICE,invoice@example.test,Invoice Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writeExtendedImportFixture("external_id,name,price_amount\nP-INVOICE,Invoice product,2500\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $orderPath = writeExtendedCsvFixture(
        ['external_id', 'customer_external_id', 'total_amount', 'currency', 'items_json'],
        ['O-PAY', 'C-INVOICE', 2500, 'EUR', json_encode([['product_external_id' => 'P-INVOICE', 'quantity' => 1, 'unit_amount' => 2500]], JSON_THROW_ON_ERROR)],
    );
    $orderRun = $executor->run($orderPath, $registry->for('csv', 'order'), 'csv');
    expect($orderRun->errors)->toBe(0);

    $invoicePath = writeExtendedCsvFixture(
        ['external_id', 'customer_external_id', 'number', 'status', 'subtotal_amount', 'tax_amount', 'total_amount', 'currency', 'items_json'],
        ['I-1', 'C-INVOICE', 'LEGACY-INV-1', 'paid', 2500, 0, 2500, 'EUR', json_encode([['kind' => 'product', 'label' => 'Invoice product', 'quantity' => 1, 'unit_amount' => 2500, 'line_total_amount' => 2500]], JSON_THROW_ON_ERROR)],
    );
    $invoiceRun = $executor->run($invoicePath, $registry->for('csv', 'invoice'), 'csv');
    $paymentPath = writeExtendedImportFixture("external_id,order_external_id,amount,currency,method,status,reference\nT-1,O-PAY,2500,EUR,legacy-gateway,paid,legacy-payment-1\n");
    $paymentRun = $executor->run($paymentPath, $registry->for('csv', 'payment'), 'csv');

    expect($invoiceRun->errors)->toBe(0)
        ->and(Invoice::query()->where('number', 'LEGACY-INV-1')->first()->status->value)->toBe('paid')
        ->and(Invoice::query()->where('number', 'LEGACY-INV-1')->first()->items)->toHaveCount(1)
        ->and($paymentRun->errors)->toBe(0)
        ->and(Payment::query()->where('reference', 'legacy-payment-1')->value('amount'))->toBe(2500)
        ->and(Payment::query()->where('reference', 'legacy-payment-1')->first()->attempts)->toHaveCount(1);

    expect(fn () => app(ImportRollback::class)->handle($paymentRun))
        ->toThrow(RuntimeException::class);

    expect(fn () => app(ImportRollback::class)->handle($invoiceRun))
        ->toThrow(RuntimeException::class);

    expect(Payment::query()->where('reference', 'legacy-payment-1')->exists())->toBeTrue()
        ->and(Invoice::query()->where('number', 'LEGACY-INV-1')->first()->status->value)->toBe('paid')
        ->and($invoiceRun->fresh()->status)->toBe('completed');

    unlink($customerPath);
    unlink($productPath);
    unlink($orderPath);
    unlink($invoicePath);
    unlink($paymentPath);
});

it('imports linked invoice addresses and properties from the order snapshot', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeExtendedImportFixture("external_id,email,name\nC-SNAPSHOT,snapshot@example.test,Current Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writeExtendedImportFixture("external_id,name,price_amount\nP-SNAPSHOT,Snapshot product,2500\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $orderPath = writeExtendedCsvFixture(
        [
            'external_id', 'customer_external_id', 'total_amount', 'currency', 'items_json',
            'billing_name', 'billing_company', 'billing_line1', 'billing_line2', 'billing_city',
            'billing_region', 'billing_postal_code', 'billing_country', 'billing_phone',
            'shipping_name', 'shipping_company', 'shipping_line1', 'shipping_line2', 'shipping_city',
            'shipping_region', 'shipping_postal_code', 'shipping_country', 'shipping_phone',
            'custom_properties_json',
        ],
        [
            'O-SNAPSHOT', 'C-SNAPSHOT', 2500, 'EUR',
            json_encode([['product_external_id' => 'P-SNAPSHOT', 'quantity' => 1, 'unit_amount' => 2500]], JSON_THROW_ON_ERROR),
            'Historical Customer', 'Historical BV', 'Historical Street 7', 'Suite 8', 'Amsterdam',
            'Noord-Holland', '1000 AA', 'NL', '+31 20 000 0000',
            'Delivery Customer', 'Delivery BV', 'Delivery Road 9', 'Dock 4', 'Rotterdam',
            'Zuid-Holland', '3000 BB', 'NL', '+31 10 000 0000',
            json_encode([['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL444444444B04']], JSON_THROW_ON_ERROR),
        ],
    );
    $orderRun = $executor->run($orderPath, $registry->for('csv', 'order'), 'csv');

    $invoicePath = writeExtendedCsvFixture(
        ['external_id', 'customer_external_id', 'order_external_id', 'number', 'status', 'subtotal_amount', 'tax_amount', 'total_amount', 'currency', 'items_json'],
        ['I-SNAPSHOT', 'C-SNAPSHOT', 'O-SNAPSHOT', 'LEGACY-INV-SNAPSHOT', 'issued', 2500, 0, 2500, 'EUR', json_encode([['kind' => 'product', 'label' => 'Snapshot product', 'quantity' => 1, 'unit_amount' => 2500, 'line_total_amount' => 2500]], JSON_THROW_ON_ERROR)],
    );
    $invoiceRun = $executor->run($invoicePath, $registry->for('csv', 'invoice'), 'csv');
    $invoice = Invoice::query()->where('number', 'LEGACY-INV-SNAPSHOT')->firstOrFail();

    expect($orderRun->errors)->toBe(0)
        ->and($invoiceRun->errors)->toBe(0)
        ->and($invoice->billing_company)->toBe('Historical BV')
        ->and($invoice->billing_line1)->toBe('Historical Street 7')
        ->and($invoice->billing_phone)->toBe('+31 20 000 0000')
        ->and($invoice->custom_properties_snapshot[0]['value'])->toBe('NL444444444B04');

    unlink($customerPath);
    unlink($productPath);
    unlink($orderPath);
    unlink($invoicePath);
});

it('imports provisioning service instances only when the module is enabled', function (): void {
    installAndEnableModules(['provisioning']);
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeExtendedImportFixture("external_id,email,name\nC-SERVICE,service@example.test,Service Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writeExtendedImportFixture("external_id,name,price_amount\nP-SERVICE,Service product,3000\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $servicePath = writeExtendedImportFixture("external_id,customer_external_id,product_external_id,number,status,provider_key,external_ref\nS-1,C-SERVICE,P-SERVICE,SVC-LEGACY,active,manual,legacy-1\n");

    $run = $executor->run($servicePath, $registry->for('csv', 'service_instance'), 'csv');

    expect($run->errors)->toBe(0)
        ->and(ServiceInstance::query()->where('number', 'SVC-LEGACY')->value('provider_key'))->toBe('manual');

    expect(fn () => app(ImportRollback::class)->handle($run))
        ->toThrow(RuntimeException::class);

    expect(ServiceInstance::query()->where('number', 'SVC-LEGACY')->exists())->toBeTrue();

    unlink($customerPath);
    unlink($productPath);
    unlink($servicePath);
});

it('rejects unsafe media paths without leaving a product image behind', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $productPath = writeExtendedImportFixture("external_id,name,price_amount\nP-SAFE,Safe product,1000\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $mediaPath = writeExtendedImportFixture("external_id,product_external_id,path\nM-SAFE,P-SAFE,../private/secret.jpg\n");

    $run = $executor->run($mediaPath, $registry->for('csv', 'media'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(ProductImage::query()->where('path', '../private/secret.jpg')->exists())->toBeFalse();

    unlink($productPath);
    unlink($mediaPath);
});
