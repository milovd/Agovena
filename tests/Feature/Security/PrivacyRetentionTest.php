<?php

declare(strict_types=1);

use App\Models\ConsentEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('prunes consent history according to the privacy retention policy', function (): void {
    config()->set('agovena.retention.consent_events_days', 30);
    $eventData = [
        'user_id' => null,
        'consent_version' => '1',
        'choice' => 'necessary',
        'source' => 'banner',
        'ip_hash' => str_repeat('a', 64),
        'user_agent_hash' => str_repeat('b', 64),
    ];
    $old = ConsentEvent::query()->create($eventData);
    $fresh = ConsentEvent::query()->create($eventData);
    DB::table('consent_events')->where('id', $old->id)->update(['created_at' => now()->subDays(31)]);

    expect(Artisan::call('agovena:prune-logs'))->toBe(0)
        ->and(ConsentEvent::query()->find($old->id))->toBeNull()
        ->and(ConsentEvent::query()->find($fresh->id))->not->toBeNull();
});
