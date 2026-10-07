<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportRollback;
use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\ImportRun;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;

function makeRollbackIntegrityRun(string $entity, string $type, int $id): ImportRun
{
    $run = ImportRun::query()->create([
        'source' => 'csv',
        'entity' => $entity,
        'mode' => 'execute',
        'status' => 'completed',
        'read' => 1,
        'valid' => 1,
        'started_at' => now()->subMinute(),
        'completed_at' => now(),
    ]);

    $run->rows()->create([
        'line' => 2,
        'entity' => $entity,
        'external_id' => 'rollback-'.$entity.'-'.$id,
        'status' => 'imported',
        'imported_model_type' => $type,
        'imported_model_id' => $id,
    ]);

    return $run;
}

it('refuses to rollback an imported customer with dependent address data', function (): void {
    $user = User::factory()->create();
    $customer = $user->customer;
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $run = makeRollbackIntegrityRun('customer', User::class, $user->id);

    expect(fn () => app(ImportRollback::class)->handle($run))
        ->toThrow(RuntimeException::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and($customer->fresh()->addresses()->whereKey($address->id)->exists())->toBeTrue()
        ->and($run->fresh()->status)->toBe('completed')
        ->and($run->rows()->firstOrFail()->status)->toBe('imported');
});

it('refuses to rollback an imported product with dependent image data', function (): void {
    $product = Product::factory()->create();
    $image = ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => 'products/retained.jpg',
        'sort' => 0,
    ]);
    $run = makeRollbackIntegrityRun('product', Product::class, $product->id);

    expect(fn () => app(ImportRollback::class)->handle($run))
        ->toThrow(RuntimeException::class);

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and(ProductImage::query()->whereKey($image->id)->exists())->toBeTrue()
        ->and($run->fresh()->status)->toBe('completed')
        ->and($run->rows()->firstOrFail()->status)->toBe('imported');
});

it('explicitly refuses to rollback an imported order', function (): void {
    $order = Order::factory()->create();
    $run = makeRollbackIntegrityRun('order', Order::class, $order->id);

    expect(fn () => app(ImportRollback::class)->handle($run))
        ->toThrow(RuntimeException::class);

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and($run->fresh()->status)->toBe('completed')
        ->and($run->rows()->firstOrFail()->status)->toBe('imported');
});

it('refuses to hard-delete an imported payment that is already paid', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);
    $run = makeRollbackIntegrityRun('payment', Payment::class, $payment->id);

    expect(fn () => app(ImportRollback::class)->handle($run))
        ->toThrow(RuntimeException::class);

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($run->fresh()->status)->toBe('completed')
        ->and($run->rows()->firstOrFail()->status)->toBe('imported');
});
