<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Operations\CronStatisticsRecorder;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CancelStaleUnpaidOrdersCommand extends Command
{
    protected $signature = 'agovena:cancel-stale-unpaid-orders';

    protected $description = 'Void unpaid invoices and cancel pending orders older than the configured grace period';

    public function handle(SettingsRepository $settings, CancelUnpaidOrder $cancel): int
    {
        $days = (int) $settings->get('store', 'unpaid_order_cancel_after_days', 0);
        if ($days < 1) {
            $this->comment('Stale unpaid-order cancellation is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $ids = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $cancelled = 0;
        foreach ($ids as $id) {
            $order = Order::query()->with(['invoice', 'payment'])->whereKey($id)->first();
            if ($order === null || ! $order->isAwaitingPayment()) {
                continue;
            }

            try {
                $cancel->handle($order, UnpaidOrderCancelSource::Scheduler);
                $cancelled++;
            } catch (ValidationException) {
                continue;
            } catch (Throwable $e) {
                $this->error("Order {$order->number}: {$e->getMessage()}");
            }
        }

        app(CronStatisticsRecorder::class)->recordRun('cancel-unpaid-orders', [
            'unpaid_orders_cancelled' => $cancelled,
        ]);
        $this->info("Cancelled {$cancelled} unpaid order(s).");

        return self::SUCCESS;
    }
}
