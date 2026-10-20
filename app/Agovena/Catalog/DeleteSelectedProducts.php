<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bulk counterpart of DeleteProduct: every product goes through the same domain action, so a
 * product that appears in orders is kept (the merchant can set it to draft instead) and its
 * images are only removed together with the product.
 */
final class DeleteSelectedProducts
{
    public const MAX_SELECTION = 500;

    public function __construct(private readonly DeleteProduct $deleteProduct) {}

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, in_orders: int, skipped: int, failed: int}
     */
    public function handle(array $ids): array
    {
        if (count($ids) > self::MAX_SELECTION) {
            throw ValidationException::withMessages(['selectedProductIds' => __('admin.products.bulk_limit')]);
        }

        $result = ['deleted' => 0, 'in_orders' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (array_unique($ids) as $id) {
            try {
                $outcome = DB::transaction(function () use ($id): string {
                    $product = Product::query()->lockForUpdate()->find($id);
                    if ($product === null || Gate::denies('products.delete', $product)) {
                        return 'skipped';
                    }
                    if ($this->deleteProduct->isReferencedByOrders($product)) {
                        return 'in_orders';
                    }
                    $this->deleteProduct->handle($product);

                    return 'deleted';
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
