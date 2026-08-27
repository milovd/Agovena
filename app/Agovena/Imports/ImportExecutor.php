<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Agovena\Imports\Contracts\ImportAdapter;
use App\Agovena\Modules\ModuleManager;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundStatus;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\ImportIdentityReservation;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class ImportExecutor
{
    public function __construct(
        private readonly CsvImportRunner $runner,
        private readonly ModuleManager $modules,
    ) {}

    public function run(
        string $path,
        ImportAdapter $adapter,
        string $source,
        bool $dryRun = false,
    ): ImportRun {
        $report = $this->runner->preview($path, $adapter);
        $run = ImportRun::query()->create([
            'source' => $source,
            'entity' => $report->candidates[0]->entity ?? 'unknown',
            'mode' => $dryRun ? 'dry_run' : 'execute',
            'status' => $dryRun ? 'preview' : 'running',
            'read' => $report->read,
            'valid' => $report->valid,
            'duplicates' => $report->duplicates,
            'errors' => $report->errors,
            'started_at' => now(),
        ]);

        DB::transaction(function () use ($run, $report, $dryRun): void {
            foreach ($report->rowErrors as $line => $message) {
                $run->rows()->create([
                    'line' => $line,
                    'entity' => $run->entity,
                    'status' => 'failed',
                    'error' => $this->safeError($message),
                ]);
            }

            foreach ($report->duplicateLines as $line => $message) {
                $run->rows()->create([
                    'line' => $line,
                    'entity' => $run->entity,
                    'status' => 'duplicate',
                    'error' => $this->safeError($message),
                ]);
            }

            foreach ($report->candidates as $candidate) {
                $row = $run->rows()->create([
                    'line' => $candidate->line,
                    'entity' => $candidate->entity,
                    'external_id' => $candidate->externalId,
                    'status' => $dryRun ? 'preview' : 'pending',
                    'payload' => $candidate->payload,
                ]);

                if ($dryRun) {
                    continue;
                }

                if (! $this->reserveIdentity($run, $row, $candidate)) {
                    $row->update(['status' => 'duplicate', 'error' => 'Source identifier was already imported.']);
                    $run->increment('duplicates');

                    continue;
                }

                try {
                    [$modelType, $modelId] = DB::transaction(
                        fn (): array => $this->createEntity($candidate),
                    );
                    $row->update([
                        'status' => 'imported',
                        'imported_model_type' => $modelType,
                        'imported_model_id' => $modelId,
                    ]);
                } catch (Throwable $exception) {
                    ImportIdentityReservation::query()
                        ->where('import_row_id', $row->id)
                        ->delete();
                    $row->update([
                        'status' => 'failed',
                        'error' => $this->safeError($exception->getMessage()),
                    ]);
                    $run->increment('errors');
                }
            }

            $run->update([
                'status' => $dryRun ? 'preview' : 'completed',
                'completed_at' => now(),
            ]);
        });

        return $run->fresh();
    }

    /** @return array{0: class-string, 1: int} */
    private function createEntity(ImportCandidate $candidate): array
    {
        return match ($candidate->entity) {
            'customer' => $this->createCustomer($candidate),
            'product' => $this->createProduct($candidate),
            'order' => $this->createOrder($candidate),
            'subscription' => $this->createSubscription($candidate),
            'invoice' => $this->createInvoice($candidate),
            'payment', 'transaction' => $this->createPayment($candidate),
            'discount', 'discount_code' => $this->createDiscountCode($candidate),
            'media', 'product_image' => $this->createProductImage($candidate),
            'service_instance', 'service' => $this->createServiceInstance($candidate),
            default => throw new InvalidArgumentException('This entity requires a dedicated dependency mapping.'),
        };
    }

    /** @return array{0: class-string, 1: int} */
    private function createSubscription(ImportCandidate $candidate): array
    {
        $modelClass = 'Agovena\\Modules\\Subscriptions\\Models\\Subscription';
        if (! $this->modules->isEnabled('subscriptions') || ! class_exists($modelClass)) {
            throw new InvalidArgumentException('The subscriptions module must be enabled before importing subscriptions.');
        }

        $source = Str::before($candidate->externalId, ':');
        $customerKey = $this->sourceKey((string) ($candidate->payload['customer_external_id'] ?? ''), $source);
        $customerRow = $this->findImportedRow($customerKey, User::class);
        $customer = User::query()->find((int) $customerRow->imported_model_id)?->customer;
        if ($customer === null) {
            throw new InvalidArgumentException('Imported customer mapping is unavailable.');
        }

        $productKey = $this->sourceKey((string) ($candidate->payload['product_external_id'] ?? ''), $source);
        $productRow = $this->findImportedRow($productKey, Product::class);
        $product = Product::query()->find((int) $productRow->imported_model_id);
        if ($product === null) {
            throw new InvalidArgumentException('Imported product mapping is unavailable.');
        }

        $status = strtolower(trim((string) ($candidate->payload['status'] ?? 'pending')));
        if (! in_array($status, ['pending', 'active', 'past_due', 'cancelled', 'ended'], true)) {
            throw new InvalidArgumentException('Subscription status is invalid.');
        }
        $interval = strtolower(trim((string) ($candidate->payload['interval'] ?? 'month')));
        if (! in_array($interval, ['day', 'week', 'month', 'year'], true)) {
            throw new InvalidArgumentException('Subscription interval is invalid.');
        }
        $intervalCount = filter_var($candidate->payload['interval_count'] ?? 1, FILTER_VALIDATE_INT);
        $quantity = filter_var($candidate->payload['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $priceAmount = filter_var($candidate->payload['price_amount'] ?? $product->price_amount, FILTER_VALIDATE_INT);
        if ($intervalCount === false || $intervalCount < 1 || $quantity === false || $quantity < 1 || $priceAmount === false || $priceAmount < 0) {
            throw new InvalidArgumentException('Subscription interval, quantity or price is invalid.');
        }

        $currency = strtoupper((string) ($candidate->payload['currency'] ?? $product->currency ?? 'EUR'));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Subscription currency is invalid.');
        }
        $number = trim((string) ($candidate->payload['number'] ?? ''));
        if ($number === '') {
            $number = 'IMP-SUB-'.strtoupper(substr(hash('sha256', $candidate->externalId), 0, 16));
        }
        $baseNumber = $number;
        $suffix = 2;
        while (DB::table('subscriptions')->where('number', $number)->exists()) {
            $number = $baseNumber.'-'.$suffix;
            $suffix++;
        }

        $start = Carbon::now();
        $end = match ($interval) {
            'day' => $start->copy()->addDays((int) $intervalCount),
            'week' => $start->copy()->addWeeks((int) $intervalCount),
            'year' => $start->copy()->addYears((int) $intervalCount),
            default => $start->copy()->addMonths((int) $intervalCount),
        };
        $subscription = app($modelClass);
        if (! $subscription instanceof Model) {
            throw new InvalidArgumentException('The subscriptions provider is invalid.');
        }
        $subscription->fill([
            'number' => $number,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_name' => $customer->name,
            'product_id' => $product->id,
            'order_id' => null,
            'order_item_id' => null,
            'status' => $status,
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'price_amount' => $priceAmount,
            'currency' => $currency,
            'quantity' => $quantity,
            'payment_gateway' => null,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'next_billing_at' => $end,
            'cancel_at_period_end' => false,
        ]);
        $subscription->save();

        return [get_class($subscription), (int) $subscription->getKey()];
    }

    /** @return array{0: class-string, 1: int} */
    private function createInvoice(ImportCandidate $candidate): array
    {
        return DB::transaction(function () use ($candidate): array {
            $source = Str::before($candidate->externalId, ':');
            $customer = $this->importedCustomer($candidate, $source);
            $order = $this->optionalImportedOrder($candidate, $source);
            if ($order !== null && (int) $order->customer_id !== (int) $customer->id) {
                throw new InvalidArgumentException('Imported invoice customer does not match its order.');
            }
            if ($order !== null && $order->invoice()->exists()) {
                throw new InvalidArgumentException('Imported order already has an invoice.');
            }

            $status = InvoiceStatus::tryFrom(strtolower(trim((string) ($candidate->payload['status'] ?? 'issued'))));
            if ($status === null) {
                throw new InvalidArgumentException('Invoice status is invalid.');
            }
            $currency = $this->currency((string) ($candidate->payload['currency'] ?? 'EUR'), 'Invoice currency');
            $items = $this->decodeJsonList($candidate->payload['items_json'] ?? null, 'Invoice items');
            $itemRows = [];
            $itemSubtotal = 0;
            foreach ($items as $item) {
                $kind = InvoiceItemKind::tryFrom(strtolower(trim((string) ($item['kind'] ?? 'product'))));
                $label = trim((string) ($item['label'] ?? ''));
                $quantity = $this->amount($item['quantity'] ?? null, 'Invoice item quantity', minimum: 1);
                $unitAmount = $this->amount($item['unit_amount'] ?? null, 'Invoice item unit amount');
                $lineAmount = $this->amount($item['line_total_amount'] ?? ($quantity * $unitAmount), 'Invoice item line amount');
                if ($kind === null || $label === '' || $lineAmount !== $quantity * $unitAmount) {
                    throw new InvalidArgumentException('Invoice item mapping is invalid.');
                }
                if (in_array($kind, [InvoiceItemKind::Product, InvoiceItemKind::Shipping], true)) {
                    $itemSubtotal += $lineAmount;
                }
                $itemRows[] = [
                    'kind' => $kind,
                    'label' => $label,
                    'quantity' => $quantity,
                    'unit_amount' => $unitAmount,
                    'line_total_amount' => $lineAmount,
                    'currency' => $currency,
                    'options_snapshot' => is_array($item['options_snapshot'] ?? null) ? $item['options_snapshot'] : null,
                ];
            }

            $subtotal = $this->amount($candidate->payload['subtotal_amount'] ?? $itemSubtotal, 'Invoice subtotal');
            if ($subtotal !== $itemSubtotal) {
                throw new InvalidArgumentException('Invoice subtotal does not match its product and shipping lines.');
            }
            $discount = $this->amount($candidate->payload['discount_amount'] ?? 0, 'Invoice discount');
            $credit = $this->amount($candidate->payload['credit_amount'] ?? 0, 'Invoice credit');
            if ($discount + $credit > $subtotal) {
                throw new InvalidArgumentException('Invoice discounts and credits exceed its subtotal.');
            }
            $tax = $this->amount($candidate->payload['tax_amount'] ?? 0, 'Invoice tax');
            $fee = $this->amount($candidate->payload['payment_fee_amount'] ?? 0, 'Invoice payment fee');
            $expectedTotal = max(0, $subtotal - $discount - $credit + $tax + $fee);
            $total = $this->amount($candidate->payload['total_amount'] ?? $expectedTotal, 'Invoice total');
            if ($total !== $expectedTotal) {
                throw new InvalidArgumentException('Invoice totals do not match the imported amounts.');
            }

            $number = trim((string) ($candidate->payload['number'] ?? ''));
            if ($number === '') {
                $number = 'IMP-INV-'.strtoupper(substr(hash('sha256', $candidate->externalId), 0, 16));
            }
            if (Invoice::query()->where('number', $number)->exists()) {
                throw new InvalidArgumentException('Imported invoice number already exists.');
            }

            $issuedAt = $this->parseDate($candidate->payload['issued_at'] ?? null, 'Invoice issue date') ?? Carbon::now();
            $paidAt = $status === InvoiceStatus::Paid
                ? ($this->parseDate($candidate->payload['paid_at'] ?? null, 'Invoice payment date') ?? Carbon::now())
                : null;
            $invoice = Invoice::query()->create([
                'number' => $number,
                'status' => $status,
                'order_id' => $order?->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'billing_name' => $customer->name,
                'issued_at' => $issuedAt,
                'due_at' => $this->parseDate($candidate->payload['due_at'] ?? null, 'Invoice due date'),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'credit_amount' => $credit,
                'tax_amount' => $tax,
                'payment_fee_amount' => $fee,
                'total_amount' => $total,
                'currency' => $currency,
                'paid_at' => $paidAt,
            ]);
            foreach ($itemRows as $itemRow) {
                $invoice->items()->create($itemRow);
            }

            return [Invoice::class, (int) $invoice->getKey()];
        });
    }

    /** @return array{0: class-string, 1: int} */
    private function createPayment(ImportCandidate $candidate): array
    {
        return DB::transaction(function () use ($candidate): array {
            $source = Str::before($candidate->externalId, ':');
            $order = $this->requiredImportedOrder($candidate, $source);
            if ($order->payment()->exists()) {
                throw new InvalidArgumentException('Imported order already has a payment transaction.');
            }
            $amount = $this->amount($candidate->payload['amount'] ?? null, 'Payment amount');
            $currency = $this->currency((string) ($candidate->payload['currency'] ?? $order->currency), 'Payment currency');
            if ($amount !== (int) $order->total_amount) {
                throw new InvalidArgumentException('Payment amount does not match the imported order total.');
            }
            if ($currency !== strtoupper((string) $order->currency)) {
                throw new InvalidArgumentException('Payment currency does not match the imported order currency.');
            }
            $method = trim((string) ($candidate->payload['method'] ?? ''));
            if ($method === '' || preg_match('/\A[A-Za-z0-9_.-]{1,32}\z/', $method) !== 1) {
                throw new InvalidArgumentException('Payment method is invalid.');
            }
            $status = PaymentStatus::tryFrom(strtolower(trim((string) ($candidate->payload['status'] ?? 'pending'))));
            if ($status === null) {
                throw new InvalidArgumentException('Payment status is invalid.');
            }
            $refundedAmount = $this->amount($candidate->payload['refunded_amount'] ?? 0, 'Payment refunded amount');
            $paid = in_array($status, [PaymentStatus::Paid, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true);
            if ($refundedAmount > 0 && ! $paid) {
                throw new InvalidArgumentException('Only paid payments can have a refunded amount.');
            }
            if ($status === PaymentStatus::PartiallyRefunded && ($refundedAmount <= 0 || $refundedAmount >= $amount)) {
                throw new InvalidArgumentException('Partially refunded payments require a refund below the payment amount.');
            }
            if ($status === PaymentStatus::Refunded && $refundedAmount !== $amount) {
                throw new InvalidArgumentException('Refunded payments require a complete refunded amount.');
            }
            if ($status === PaymentStatus::Paid && $refundedAmount !== 0) {
                throw new InvalidArgumentException('Paid payments cannot carry a refunded amount.');
            }
            $paidAt = $paid
                ? ($this->parseDate($candidate->payload['paid_at'] ?? null, 'Payment date') ?? Carbon::now())
                : null;
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'amount' => $amount,
                'currency' => $currency,
                'method' => $method,
                'status' => $status,
                'paid_at' => $paidAt,
                'reference' => trim((string) ($candidate->payload['reference'] ?? '')) ?: null,
            ]);
            PaymentAttempt::query()->create([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'gateway_id' => $method,
                'status' => $paid ? PaymentAttemptStatus::Succeeded : match ($status) {
                    PaymentStatus::Cancelled => PaymentAttemptStatus::Cancelled,
                    PaymentStatus::Failed => PaymentAttemptStatus::Failed,
                    PaymentStatus::Expired => PaymentAttemptStatus::Expired,
                    default => PaymentAttemptStatus::Pending,
                },
                'external_id' => $candidate->externalId,
                'amount' => $amount,
                'currency' => $currency,
                'idempotency_key' => 'import:'.$candidate->externalId,
                'request_meta' => ['source' => 'import'],
                'initiated_at' => Carbon::now(),
                'completed_at' => $paid ? $paidAt : null,
            ]);
            if ($refundedAmount > 0) {
                Refund::query()->create([
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice?->id,
                    'amount' => $refundedAmount,
                    'currency' => $currency,
                    'status' => RefundStatus::Completed,
                    'reason' => 'Historical refund imported.',
                    'completed_at' => $this->parseDate($candidate->payload['refunded_at'] ?? null, 'Refund date') ?? $paidAt ?? Carbon::now(),
                ]);
            }

            return [Payment::class, (int) $payment->getKey()];
        });
    }

    /** @return array{0: class-string, 1: int} */
    private function createDiscountCode(ImportCandidate $candidate): array
    {
        $code = strtoupper(trim((string) ($candidate->payload['code'] ?? '')));
        if ($code === '' || strlen($code) > 255 || preg_match('/\A[A-Z0-9][A-Z0-9_-]*\z/', $code) !== 1) {
            throw new InvalidArgumentException('Discount code is invalid.');
        }
        if (DiscountCode::query()->where('code', $code)->exists()) {
            throw new InvalidArgumentException('Discount code already exists.');
        }
        $type = strtolower(trim((string) ($candidate->payload['type'] ?? '')));
        if (! in_array($type, ['fixed', 'percent'], true)) {
            throw new InvalidArgumentException('Discount type is invalid.');
        }
        $value = $this->amount($candidate->payload['value'] ?? null, 'Discount value');
        if ($type === 'percent' && $value > 100) {
            throw new InvalidArgumentException('Discount percentage is invalid.');
        }
        $currency = trim((string) ($candidate->payload['currency'] ?? ''));
        $currency = $type === 'fixed' ? $this->currency($currency, 'Discount currency') : null;
        $active = filter_var($candidate->payload['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            throw new InvalidArgumentException('Discount active flag is invalid.');
        }
        $discount = DiscountCode::query()->create([
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'currency' => $currency,
            'starts_at' => $this->parseDate($candidate->payload['starts_at'] ?? null, 'Discount start date'),
            'ends_at' => $this->parseDate($candidate->payload['ends_at'] ?? null, 'Discount end date'),
            'max_uses' => $this->nullableAmount($candidate->payload['max_uses'] ?? null, 'Discount usage limit'),
            'max_uses_per_customer' => $this->nullableAmount($candidate->payload['max_uses_per_customer'] ?? null, 'Discount customer usage limit'),
            'min_subtotal_amount' => $this->amount($candidate->payload['min_subtotal_amount'] ?? 0, 'Discount minimum subtotal'),
            'is_active' => $active,
        ]);

        return [DiscountCode::class, (int) $discount->getKey()];
    }

    /** @return array{0: class-string, 1: int} */
    private function createProductImage(ImportCandidate $candidate): array
    {
        $source = Str::before($candidate->externalId, ':');
        $product = $this->requiredImportedProduct($candidate, $source);
        $path = trim((string) ($candidate->payload['path'] ?? ''));
        $segments = explode('/', $path);
        if ($path === '' || str_contains($path, '\\') || str_contains($path, '//') || in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]{0,511}\z/', $path) !== 1) {
            throw new InvalidArgumentException('Product media path is invalid.');
        }
        $sort = $this->amount($candidate->payload['sort'] ?? 0, 'Product media sort', maximum: 65535);
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => $path,
            'sort' => $sort,
        ]);

        return [ProductImage::class, (int) $image->getKey()];
    }

    /** @return array{0: class-string, 1: int} */
    private function createServiceInstance(ImportCandidate $candidate): array
    {
        $serviceClass = 'Agovena\\Modules\\Provisioning\\Models\\ServiceInstance';
        if (! $this->modules->isEnabled('provisioning') || ! class_exists($serviceClass) || ! is_a($serviceClass, Model::class, true)) {
            throw new InvalidArgumentException('The provisioning module must be enabled before importing service instances.');
        }

        return DB::transaction(function () use ($candidate, $serviceClass): array {
            $source = Str::before($candidate->externalId, ':');
            $customer = $this->importedCustomer($candidate, $source);
            $order = $this->optionalImportedOrder($candidate, $source);
            if ($order !== null && (int) $order->customer_id !== (int) $customer->id) {
                throw new InvalidArgumentException('Imported service customer does not match its order.');
            }
            $product = $this->optionalImportedProduct($candidate, $source);
            $status = strtolower(trim((string) ($candidate->payload['status'] ?? 'pending')));
            if (! in_array($status, ['pending', 'provisioning', 'active', 'suspended', 'terminated', 'failed', 'manual_review'], true)) {
                throw new InvalidArgumentException('Service instance status is invalid.');
            }
            $subscriptionId = null;
            $subscriptionExternalId = trim((string) ($candidate->payload['subscription_external_id'] ?? ''));
            if ($subscriptionExternalId !== '') {
                $subscriptionClass = 'Agovena\\Modules\\Subscriptions\\Models\\Subscription';
                if (! $this->modules->isEnabled('subscriptions') || ! class_exists($subscriptionClass)) {
                    throw new InvalidArgumentException('The subscriptions module must be enabled before mapping a service subscription.');
                }
                $subscriptionId = $this->findImportedRow($this->sourceKey($subscriptionExternalId, $source), $subscriptionClass)->imported_model_id;
                $subscription = $subscriptionClass::query()->find((int) $subscriptionId);
                if (! $subscription instanceof Model) {
                    throw new InvalidArgumentException('Imported subscription mapping is unavailable.');
                }
                if ((int) $subscription->getAttribute('customer_id') !== (int) $customer->id) {
                    throw new InvalidArgumentException('Imported service subscription customer does not match the service customer.');
                }
                if ($product === null || (int) $subscription->getAttribute('product_id') !== (int) $product->id) {
                    throw new InvalidArgumentException('Imported service subscription product does not match the service product.');
                }
            }
            $number = trim((string) ($candidate->payload['number'] ?? ''));
            if ($number === '') {
                $number = 'IMP-SVC-'.strtoupper(substr(hash('sha256', $candidate->externalId), 0, 16));
            }
            if (DB::table('service_instances')->where('number', $number)->exists()) {
                throw new InvalidArgumentException('Imported service instance number already exists.');
            }
            $model = app($serviceClass);
            if (! $model instanceof Model) {
                throw new InvalidArgumentException('The provisioning provider is invalid.');
            }
            $model->fill([
                'number' => $number,
                'order_id' => $order?->id,
                'order_item_id' => null,
                'product_id' => $product?->id,
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'customer_name' => $customer->name,
                'subscription_id' => $subscriptionId,
                'status' => $status,
                'provider_key' => trim((string) ($candidate->payload['provider_key'] ?? '')) ?: null,
                'external_ref' => trim((string) ($candidate->payload['external_ref'] ?? '')) ?: null,
                'meta' => $this->decodeJsonObject($candidate->payload['meta_json'] ?? null, 'Service metadata'),
            ]);
            $model->save();

            return [$serviceClass, (int) $model->getKey()];
        });
    }

    private function importedCustomer(ImportCandidate $candidate, string $source): Customer
    {
        $externalId = trim((string) ($candidate->payload['customer_external_id'] ?? ''));
        if ($externalId === '') {
            throw new InvalidArgumentException('Imported customer mapping is required.');
        }
        $row = $this->findImportedRow($this->sourceKey($externalId, $source), User::class);
        $customer = User::query()->find((int) $row->imported_model_id)?->customer;
        if ($customer === null) {
            throw new InvalidArgumentException('Imported customer mapping is unavailable.');
        }

        return $customer;
    }

    private function requiredImportedOrder(ImportCandidate $candidate, string $source): Order
    {
        $externalId = trim((string) ($candidate->payload['order_external_id'] ?? ''));
        if ($externalId === '') {
            throw new InvalidArgumentException('Imported order mapping is required.');
        }
        $row = $this->findImportedRow($this->sourceKey($externalId, $source), Order::class);
        $order = Order::query()->find((int) $row->imported_model_id);
        if ($order === null) {
            throw new InvalidArgumentException('Imported order mapping is unavailable.');
        }

        return $order;
    }

    private function optionalImportedOrder(ImportCandidate $candidate, string $source): ?Order
    {
        $externalId = trim((string) ($candidate->payload['order_external_id'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        return $this->requiredImportedOrder($candidate, $source);
    }

    private function requiredImportedProduct(ImportCandidate $candidate, string $source): Product
    {
        $externalId = trim((string) ($candidate->payload['product_external_id'] ?? ''));
        if ($externalId === '') {
            throw new InvalidArgumentException('Imported product mapping is required.');
        }
        $row = $this->findImportedRow($this->sourceKey($externalId, $source), Product::class);
        $product = Product::query()->find((int) $row->imported_model_id);
        if ($product === null) {
            throw new InvalidArgumentException('Imported product mapping is unavailable.');
        }

        return $product;
    }

    private function optionalImportedProduct(ImportCandidate $candidate, string $source): ?Product
    {
        $externalId = trim((string) ($candidate->payload['product_external_id'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        return $this->requiredImportedProduct($candidate, $source);
    }

    /** @return list<array<string, mixed>> */
    private function decodeJsonList(mixed $value, string $label): array
    {
        $decoded = $this->decodeJsonObject($value, $label);
        if ($decoded === null || ! array_is_list($decoded) || $decoded === []) {
            throw new InvalidArgumentException($label.' must be a non-empty JSON list.');
        }

        $items = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException($label.' contains an invalid item.');
            }
            $items[] = $item;
        }

        return $items;
    }

    /** @return array<int|string, mixed>|null */
    private function decodeJsonObject(mixed $value, string $label): ?array
    {
        $json = trim((string) ($value ?? ''));
        if ($json === '') {
            return null;
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new InvalidArgumentException($label.' contains invalid JSON.');
        }
        if (! is_array($decoded)) {
            throw new InvalidArgumentException($label.' must be a JSON object or list.');
        }

        return $decoded;
    }

    private function amount(mixed $value, string $label, int $minimum = 0, ?int $maximum = null): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < $minimum || ($maximum !== null && $parsed > $maximum)) {
            throw new InvalidArgumentException($label.' is invalid.');
        }

        return (int) $parsed;
    }

    private function nullableAmount(mixed $value, string $label): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->amount($value, $label);
    }

    private function currency(string $value, string $label): string
    {
        $currency = strtoupper(trim($value));
        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new InvalidArgumentException($label.' is invalid.');
        }

        return $currency;
    }

    private function parseDate(mixed $value, string $label): ?Carbon
    {
        $date = trim((string) ($value ?? ''));
        if ($date === '') {
            return null;
        }
        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            throw new InvalidArgumentException($label.' is invalid.');
        }
    }

    /** @return array{0: class-string, 1: int} */
    private function createCustomer(ImportCandidate $candidate): array
    {
        $email = strtolower(trim((string) ($candidate->payload['email'] ?? '')));
        $name = trim((string) ($candidate->payload['name'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            throw new InvalidArgumentException('Customer email and name are required.');
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException('A customer with this email already exists.');
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(64),
        ]);

        return [User::class, (int) $user->getKey()];
    }

    /** @return array{0: class-string, 1: int} */
    private function createProduct(ImportCandidate $candidate): array
    {
        $name = trim((string) ($candidate->payload['name'] ?? ''));
        $price = filter_var($candidate->payload['price_amount'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || $price === false || $price < 0) {
            throw new InvalidArgumentException('Product name and non-negative minor-unit price are required.');
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            throw new InvalidArgumentException('Product name cannot produce a safe slug.');
        }
        $baseSlug = $slug;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $product = Product::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'status' => ProductStatus::Draft,
            'price_amount' => $price,
            'currency' => strtoupper((string) ($candidate->payload['currency'] ?? 'EUR')),
        ]);

        return [Product::class, (int) $product->getKey()];
    }

    /** @return array{0: class-string, 1: int} */
    private function createOrder(ImportCandidate $candidate): array
    {
        return DB::transaction(function () use ($candidate): array {
            $source = Str::before($candidate->externalId, ':');
            $customerKey = $this->sourceKey((string) ($candidate->payload['customer_external_id'] ?? ''), $source);
            $customerRow = $this->findImportedRow($customerKey, User::class);
            $user = User::query()->find((int) $customerRow->imported_model_id);
            $customer = $user?->customer;
            if ($customer === null) {
                throw new InvalidArgumentException('Imported customer mapping is unavailable.');
            }

            $total = filter_var($candidate->payload['total_amount'] ?? null, FILTER_VALIDATE_INT);
            $items = json_decode((string) ($candidate->payload['items_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if ($total === false || $total < 0 || ! is_array($items) || $items === []) {
                throw new InvalidArgumentException('Order total and line items are required.');
            }

            $currency = strtoupper((string) ($candidate->payload['currency'] ?? 'EUR'));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new InvalidArgumentException('Order currency is invalid.');
            }

            $lineTotal = 0;
            $resolvedItems = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException('Order line item is invalid.');
                }
                $productKey = $this->sourceKey((string) ($item['product_external_id'] ?? ''), $source);
                $productRow = $this->findImportedRow($productKey, Product::class);
                $product = Product::query()->find((int) $productRow->imported_model_id);
                $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
                $unitAmount = filter_var($item['unit_amount'] ?? null, FILTER_VALIDATE_INT);
                if ($product === null || $quantity === false || $quantity < 1 || $unitAmount === false || $unitAmount < 0) {
                    throw new InvalidArgumentException('Order line item mapping is invalid.');
                }
                $lineAmount = $quantity * $unitAmount;
                $lineTotal += $lineAmount;
                $resolvedItems[] = [$product, $quantity, $unitAmount, $lineAmount, trim((string) ($item['label'] ?? $product->name))];
            }
            if ($lineTotal !== (int) $total) {
                throw new InvalidArgumentException('Order line items do not match the imported total.');
            }

            $number = trim((string) ($candidate->payload['number'] ?? ''));
            if ($number === '') {
                $number = 'IMP-'.strtoupper(substr(hash('sha256', $candidate->externalId), 0, 16));
            }
            if (Order::query()->where('number', $number)->exists()) {
                throw new InvalidArgumentException('Imported order number already exists.');
            }

            $order = Order::query()->create([
                'number' => $number,
                'status' => OrderStatus::Pending,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'subtotal_amount' => (int) $total,
                'shipping_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'payment_fee_amount' => 0,
                'credit_amount' => 0,
                'total_amount' => (int) $total,
                'currency' => $currency,
                'idempotency_key' => 'import:'.$candidate->externalId,
            ]);

            foreach ($resolvedItems as [$product, $quantity, $unitAmount, $lineAmount, $label]) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'label' => $label,
                    'quantity' => $quantity,
                    'unit_amount' => $unitAmount,
                    'line_total_amount' => $lineAmount,
                    'currency' => $currency,
                ]);
            }

            return [Order::class, (int) $order->id];
        });
    }

    private function sourceKey(string $value, string $source): string
    {
        return str_contains($value, ':') ? $value : $source.':'.$value;
    }

    private function findImportedRow(string $externalId, string $modelType): ImportRow
    {
        $row = ImportRow::query()
            ->where('external_id', $externalId)
            ->where('status', 'imported')
            ->where('imported_model_type', $modelType)
            ->latest('id')
            ->first();
        if ($row === null) {
            throw new InvalidArgumentException('Imported dependency mapping is unavailable.');
        }

        return $row;
    }

    private function safeError(string $message): string
    {
        return mb_substr(trim($message !== '' ? $message : 'Import row failed.'), 0, 500);
    }

    private function reserveIdentity(ImportRun $run, ImportRow $row, ImportCandidate $candidate): bool
    {
        if (ImportRow::query()
            ->where('external_id', $candidate->externalId)
            ->where('entity', $candidate->entity)
            ->where('status', 'imported')
            ->whereHas('run', fn ($query) => $query->where('source', $run->source))
            ->exists()) {
            return false;
        }

        try {
            ImportIdentityReservation::query()->create([
                'source' => $run->source,
                'entity' => $candidate->entity,
                'external_id' => $candidate->externalId,
                'import_run_id' => $run->id,
                'import_row_id' => $row->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException $exception) {
            $alreadyReserved = ImportIdentityReservation::query()
                ->where('source', $run->source)
                ->where('entity', $candidate->entity)
                ->where('external_id', $candidate->externalId)
                ->exists();
            if (! $alreadyReserved) {
                throw $exception;
            }

            return false;
        }
    }
}
