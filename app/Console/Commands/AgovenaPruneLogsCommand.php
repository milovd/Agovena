<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Operations\CronStatisticsRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AgovenaPruneLogsCommand extends Command
{
    protected $signature = 'agovena:prune-logs';

    protected $description = 'Remove expired operational logs without touching financial records';

    public function handle(): int
    {
        $emailDays = max(1, (int) config('agovena.retention.email_logs_days', 90));
        $auditDays = max(1, (int) config('agovena.retention.audit_logs_days', 365));
        $webhookDays = max(1, (int) config('agovena.retention.webhook_events_days', 90));
        $consentDays = max(1, (int) config('agovena.retention.consent_events_days', 365));

        $email = 0;
        $audit = 0;
        $webhooks = 0;
        $consents = 0;

        if (Schema::hasTable('email_logs')) {
            $email = DB::table('email_logs')->where('created_at', '<', now()->subDays($emailDays))->delete();
        }
        if (Schema::hasTable('audit_logs')) {
            $audit = DB::table('audit_logs')->where('created_at', '<', now()->subDays($auditDays))->delete();
        }
        if (Schema::hasTable('payment_webhook_events')) {
            $webhooks = DB::table('payment_webhook_events')
                ->whereIn('processing_status', ['processed', 'ignored', 'duplicate'])
                ->where('created_at', '<', now()->subDays($webhookDays))
                ->delete();
        }
        if (Schema::hasTable('consent_events')) {
            $consents = DB::table('consent_events')
                ->where('created_at', '<', now()->subDays($consentDays))
                ->delete();
        }

        $total = $email + $audit + $webhooks + $consents;
        app(CronStatisticsRecorder::class)->recordRun('prune-logs', [
            'logs_pruned' => $total,
        ]);
        $this->info("Pruned email_logs={$email} audit_logs={$audit} payment_webhook_events={$webhooks} consent_events={$consents}");

        return self::SUCCESS;
    }
}
