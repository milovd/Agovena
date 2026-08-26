<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Agovena\Imports\Contracts\ImportAdapter;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class ImportExecutor
{
    public function __construct(private readonly CsvImportRunner $runner) {}

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

                if (ImportRow::query()
                    ->where('external_id', $candidate->externalId)
                    ->where('status', 'imported')
                    ->exists()) {
                    $row->update(['status' => 'duplicate', 'error' => 'Source identifier was already imported.']);
                    $run->increment('duplicates');

                    continue;
                }

                try {
                    [$modelType, $modelId] = $this->createEntity($candidate);
                    $row->update([
                        'status' => 'imported',
                        'imported_model_type' => $modelType,
                        'imported_model_id' => $modelId,
                    ]);
                } catch (Throwable $exception) {
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
            default => throw new InvalidArgumentException('This entity requires a dedicated dependency mapping.'),
        };
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
}
