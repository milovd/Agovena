<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Payments\HandlePaymentWebhook;
use App\Models\PaymentWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileDeferredPaymentWebhooksCommand extends Command
{
    protected $signature = 'agovena:reconcile-payment-webhooks {--limit=100}';

    protected $description = 'Reconcile verified payment webhooks that arrived before their payment attempt';

    public function handle(HandlePaymentWebhook $webhooks): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $processed = 0;
        $failed = 0;

        $query = PaymentWebhookEvent::query()
            ->whereIn('processing_status', ['received', 'deferred']);
        $events = (clone $query)
            ->whereNotNull('external_payment_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $remaining = $limit - $events->count();
        if ($remaining > 0) {
            $events = $events->concat(
                (clone $query)
                    ->whereNull('external_payment_id')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->get(),
            );
        }

        $events->each(function (PaymentWebhookEvent $event) use ($webhooks, &$processed, &$failed): void {
            try {
                $result = $webhooks->reconcileDeferred($event);
                if (! $result->duplicate) {
                    $processed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                Log::error('payment.webhook.reconciliation_failed', [
                    'gateway_id' => $event->gateway_id,
                    'webhook_event_id' => $event->id,
                    'exception' => $exception::class,
                ]);
                $this->error('Could not reconcile payment webhook event '.$event->id.'.');
            }
        });

        $this->info(sprintf('Reconciled %d deferred payment webhook(s); %d failed.', $processed, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
