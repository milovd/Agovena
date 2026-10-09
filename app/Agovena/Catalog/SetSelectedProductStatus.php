<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SetSelectedProductStatus
{
    public function __construct(private readonly SetProductStatus $setProductStatus) {}

    /**
     * @param  list<int>  $ids
     * @return array{updated: int, unchanged: int, skipped: int, failed: int}
     */
    public function handle(array $ids, string $status): array
    {
        if (! in_array($status, [ProductStatus::Draft->value, ProductStatus::Active->value], true)) {
            throw ValidationException::withMessages(['bulkStatus' => __('admin.products.bulk_invalid_status')]);
        }
        if (count($ids) > 500) {
            throw ValidationException::withMessages(['selectedProductIds' => __('admin.products.bulk_limit')]);
        }

        $result = ['updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (array_unique($ids) as $id) {
            try {
                $outcome = DB::transaction(function () use ($id, $status): string {
                    $product = Product::query()->lockForUpdate()->find($id);
                    if ($product === null || Gate::denies('products.update', $product)) {
                        return 'skipped';
                    }
                    if ($product->status->value === $status) {
                        return 'unchanged';
                    }
                    $this->setProductStatus->handle($product, $status);

                    return 'updated';
                });
                $result[$outcome]++;
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
            }
        }

        return $result;
    }
}
