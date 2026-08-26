<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Models\Customer;
use App\Models\ImportRun;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ImportRollback
{
    public function handle(ImportRun $run): ImportRun
    {
        if ($run->status !== 'completed') {
            throw new RuntimeException('Only completed import runs can be rolled back.');
        }

        DB::transaction(function () use ($run): void {
            foreach ($run->rows()->where('status', 'imported')->orderByDesc('id')->get() as $row) {
                $this->deleteImportedModel((string) $row->imported_model_type, (int) $row->imported_model_id);
                $row->update(['status' => 'rolled_back']);
            }

            $run->update([
                'status' => 'rolled_back',
                'completed_at' => now(),
            ]);
        });

        return $run->fresh();
    }

    private function deleteImportedModel(string $type, int $id): void
    {
        if ($type === User::class) {
            $user = User::query()->find($id);
            if ($user === null) {
                return;
            }

            $customer = $user->customer;
            if ($customer instanceof Customer && $customer->orders()->exists()) {
                throw new RuntimeException('Imported customer has orders and cannot be rolled back safely.');
            }
            $customer?->delete();
            $user->delete();

            return;
        }

        if ($type === Product::class) {
            $product = Product::query()->find($id);
            if ($product !== null && $product->isReferencedByOrders()) {
                throw new RuntimeException('Imported product is referenced by an order and cannot be rolled back safely.');
            }
            $product?->delete();

            return;
        }

        throw new RuntimeException('Import rollback encountered an unsupported model type.');
    }
}
