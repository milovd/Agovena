<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Webhooks\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

final class DeliverPendingWebhooksCommand extends Command
{
    protected $signature = 'agovena:deliver-webhooks {--limit=100}';

    protected $description = 'Queue pending and retryable outbound webhook deliveries';

    public function handle(): int
    {
        $limit = min(500, max(1, (int) $this->option('limit')));
        $deliveries = WebhookDelivery::query()
            ->where(function ($query): void {
                $query->where(function ($retryable): void {
                    $retryable->where('status', 'retrying')
                        ->where(function ($due): void {
                            $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                        });
                })->orWhere(function ($orphaned): void {
                    $orphaned->where('status', 'queued')
                        ->where('created_at', '<=', now()->subMinute());
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'in_progress')
                        ->where('updated_at', '<=', now()->subMinutes(10));
                });
            })
            ->whereHas('endpoint', fn ($query) => $query->where('active', true))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            if ($delivery->status === 'in_progress') {
                $delivery->update([
                    'status' => 'retrying',
                    'next_attempt_at' => now(),
                ]);
            }
            DeliverWebhook::dispatch($delivery->id);
        }

        $this->info('Queued '.$deliveries->count().' outbound webhook deliveries.');

        return self::SUCCESS;
    }
}
