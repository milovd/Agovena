<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Agovena\Audit\AuditLogger;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Change a product's publication status from Admin, with an audit record of the change.
 */
final class SetProductStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Product $product, string $status): void
    {
        $next = ProductStatus::tryFrom($status);
        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => __('admin.products.validation.status_invalid'),
            ]);
        }

        $previous = $product->status;
        if ($previous === $next) {
            return;
        }

        $product->forceFill(['status' => $next])->save();

        $this->audit->logChange(
            'product.status_changed',
            $product,
            ['status' => $previous->value],
            ['status' => $next->value],
        );
    }
}
