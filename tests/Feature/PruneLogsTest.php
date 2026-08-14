<?php

declare(strict_types=1);

use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('prune logs removes expired operational rows and leaves financial orders', function () {
    DB::table('email_logs')->insert([
        'to' => 'old@example.test',
        'subject' => 'Old',
        'status' => 'sent',
        'created_at' => now()->subDays(120),
    ]);
    DB::table('email_logs')->insert([
        'to' => 'new@example.test',
        'subject' => 'New',
        'status' => 'sent',
        'created_at' => now()->subDay(),
    ]);
    PaymentWebhookEvent::query()->create([
        'gateway_id' => 'manual',
        'external_event_id' => 'evt-old',
        'status' => 'paid',
        'processing_status' => 'processed',
    ]);
    PaymentWebhookEvent::query()->where('external_event_id', 'evt-old')->update([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ]);
    PaymentWebhookEvent::query()->create([
        'gateway_id' => 'manual',
        'external_event_id' => 'evt-pending',
        'status' => 'paid',
        'processing_status' => 'received',
    ]);
    PaymentWebhookEvent::query()->where('external_event_id', 'evt-pending')->update([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ]);

    Artisan::call('agovena:prune-logs');

    expect(DB::table('email_logs')->where('to', 'old@example.test')->exists())->toBeFalse()
        ->and(DB::table('email_logs')->where('to', 'new@example.test')->exists())->toBeTrue()
        ->and(PaymentWebhookEvent::query()->where('external_event_id', 'evt-old')->exists())->toBeFalse()
        ->and(PaymentWebhookEvent::query()->where('external_event_id', 'evt-pending')->exists())->toBeTrue();
});
