<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

use App\Agovena\Exports\Datasets\CustomersExport;
use App\Agovena\Exports\Datasets\InventoryExport;
use App\Agovena\Exports\Datasets\InvoicesExport;
use App\Agovena\Exports\Datasets\OrdersExport;
use App\Agovena\Exports\Datasets\PaymentsExport;
use App\Agovena\Exports\Datasets\ProductsExport;
use App\Agovena\Exports\Datasets\SubscriptionsExport;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Container\Container;

/**
 * The Core entities staff can export. A dataset is available to a staff member
 * only when they hold data.export and the dataset's own view permission.
 */
final class ExportDatasetRegistry
{
    public const PERMISSION = 'data.export';

    /** @var list<class-string<ExportDataset>> */
    private const DATASETS = [
        CustomersExport::class,
        ProductsExport::class,
        OrdersExport::class,
        InvoicesExport::class,
        PaymentsExport::class,
        SubscriptionsExport::class,
        InventoryExport::class,
    ];

    /** @var array<string, ExportDataset>|null */
    private ?array $datasets = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, ExportDataset> */
    public function all(): array
    {
        if ($this->datasets === null) {
            $this->datasets = [];
            foreach (self::DATASETS as $class) {
                /** @var ExportDataset $dataset */
                $dataset = $this->container->make($class);
                $this->datasets[$dataset->key()] = $dataset;
            }
        }

        return $this->datasets;
    }

    public function find(string $key): ?ExportDataset
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, ExportDataset> */
    public function availableFor(?Authorizable $user): array
    {
        if ($user === null || ! $user->can(self::PERMISSION)) {
            return [];
        }

        return array_filter(
            $this->all(),
            static fn (ExportDataset $dataset): bool => $user->can($dataset->permission()),
        );
    }
}
