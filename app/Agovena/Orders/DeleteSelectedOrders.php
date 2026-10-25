<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bulk counterpart of DeleteOrder: every order goes through the same guarded domain action,
 * so an order with financial history is kept and reported instead of being deleted.
 */
final class DeleteSelectedOrders
{
    public const MAX_SELECTION = 500;

    public function __construct(private readonly DeleteOrder $deleteOrder) {}

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, kept: int, skipped: int, failed: int}
     */
    public function handle(array $ids, User $staff): array
    {
        if (count($ids) > self::MAX_SELECTION) {
            throw ValidationException::withMessages(['selectedOrderIds' => __('admin.orders.bulk_limit')]);
        }

        $result = ['deleted' => 0, 'kept' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (array_unique($ids) as $id) {
            $order = Order::query()->find($id);
            if ($order === null || ! $staff->can('orders.delete')) {
                $result['skipped']++;

                continue;
            }

            try {
                $this->deleteOrder->handle($order, $staff);
                $result['deleted']++;
            } catch (ValidationException) {
                // DeleteOrder refuses orders that are no longer open or have financial history.
                $result['kept']++;
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
            }
        }

        return $result;
    }
}
