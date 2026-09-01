<?php

declare(strict_types=1);

use App\Queue\AfterCommitFailoverQueue;
use App\Queue\AfterCommitQueueOutbox;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('failover queue defers raw delivery until the transaction commits', function () {
    $delivered = false;
    $backend = Mockery::mock();
    $backend->shouldReceive('pushRaw')->once()->andReturnUsing(function () use (&$delivered): string {
        $delivered = true;

        return 'job-id';
    });

    $manager = Mockery::mock(QueueManager::class);
    $manager->shouldReceive('connection')->once()->with('first')->andReturn($backend);

    $queue = new AfterCommitFailoverQueue(
        $manager,
        Mockery::mock(Dispatcher::class),
        ['first'],
    );
    $queue->setContainer(app());

    DB::transaction(function () use ($queue, &$delivered): void {
        $queue->pushRaw('{"job":"fixture"}', 'default');
        expect($delivered)->toBeFalse();
    });

    expect($delivered)->toBeTrue();
});

test('failover queue uses its configured queue name when none is supplied', function () {
    config([
        'queue.connections.first.queue' => 'configured-queue',
        'queue.connections.failover.queue' => 'configured-queue',
    ]);
    $backend = Mockery::mock();
    $backend->shouldReceive('pushRaw')->once()->withArgs(function (string $payload, string $queue): bool {
        return $payload === '{"job":"fixture"}' && $queue === 'configured-queue';
    })->andReturn('job-id');
    $manager = Mockery::mock(QueueManager::class);
    $manager->shouldReceive('connection')->once()->with('first')->andReturn($backend);
    $queue = new AfterCommitFailoverQueue($manager, Mockery::mock(Dispatcher::class), ['first']);
    $queue->setContainer(app());

    expect($queue->pushRaw('{"job":"fixture"}'))->toBe('job-id');
});

test('failed after-commit enqueue is journaled durably', function () {
    DB::table('queue_outboxes')->delete();

    $manager = Mockery::mock(QueueManager::class);
    $manager->shouldReceive('connection')
        ->twice()
        ->andThrow(new RuntimeException('queue backend unavailable'));

    $queue = new AfterCommitFailoverQueue(
        $manager,
        Mockery::mock(Dispatcher::class),
        ['first', 'second'],
    );
    $queue->setContainer(app());

    expect(fn () => $queue->pushRaw('{"job":"fixture"}', 'default'))
        ->toThrow(RuntimeException::class, 'queue backend unavailable');

    expect(DB::table('queue_outboxes')->count())->toBe(1)
        ->and(DB::table('queue_outboxes')->value('payload_encrypted'))->not->toContain('fixture');
});

test('queue outbox recovery requeues a journaled payload', function () {
    DB::table('jobs')->delete();
    DB::table('queue_outboxes')->delete();

    app(AfterCommitQueueOutbox::class)->store('{"job":"fixture"}', 'default');

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('queue_outboxes')->whereNull('completed_at')->count())->toBe(0);

    Queue::connection('database')->size('default');
});

test('queue outbox recovery preserves delayed payload availability', function () {
    DB::table('jobs')->delete();
    DB::table('queue_outboxes')->delete();
    $payload = json_encode([
        'uuid' => 'delayed-fixture',
        'job' => 'fixture',
        'delay' => 300,
        'data' => [],
    ], JSON_THROW_ON_ERROR);
    $id = app(AfterCommitQueueOutbox::class)->store($payload, 'default');

    expect(DB::table('queue_outboxes')->where('id', $id)->value('available_at'))->toBeGreaterThan(now()->addSeconds(250))
        ->and(app(AfterCommitQueueOutbox::class)->recover())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('queue outbox recovery does not restart an already elapsed delay', function () {
    DB::table('jobs')->delete();
    DB::table('queue_outboxes')->delete();
    $payload = json_encode([
        'uuid' => 'elapsed-delay-fixture',
        'job' => 'fixture',
        'delay' => 300,
        'data' => [],
    ], JSON_THROW_ON_ERROR);
    $id = app(AfterCommitQueueOutbox::class)->store($payload, 'default');
    DB::table('queue_outboxes')->where('id', $id)->update(['available_at' => now()->subSecond()]);

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(1)
        ->and(DB::table('jobs')->value('available_at'))->toBeLessThanOrEqual(now()->timestamp + 1);
});

test('queue outbox falls back to encrypted local recovery when database journaling fails', function () {
    Storage::fake('local');
    DB::shouldReceive('table')->once()->andThrow(new RuntimeException('outbox database unavailable'));

    expect(app(AfterCommitQueueOutbox::class)->store('{"secret":"[REDACTED]"}', 'default'))->toBe(0);

    $files = Storage::disk('local')->files('queue-outbox-failover');
    expect($files)->toHaveCount(1)
        ->and(Storage::disk('local')->get($files[0]))->not->toContain('[REDACTED]');
});

test('queue outbox recovers encrypted local fallback entries', function () {
    Storage::fake('local');
    DB::table('jobs')->delete();

    app(AfterCommitQueueOutbox::class)->storeFallback('{"job":"fixture"}', 'default');

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(Storage::disk('local')->files('queue-outbox-failover'))->toBe([]);
});

test('queue outbox tries each configured recovery connection', function () {
    DB::table('queue_outboxes')->delete();
    config(['queue.connections.failover.connections' => ['unavailable', 'database']]);

    $unavailable = Mockery::mock();
    $unavailable->shouldReceive('pushRaw')->once()->andThrow(new RuntimeException('backend unavailable'));
    $available = Mockery::mock();
    $available->shouldReceive('pushRaw')->once();
    Queue::shouldReceive('connection')->with('unavailable')->once()->andReturn($unavailable);
    Queue::shouldReceive('connection')->with('database')->once()->andReturn($available);

    app(AfterCommitQueueOutbox::class)->store('{"job":"fixture"}', 'default');

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(1)
        ->and(DB::table('queue_outboxes')->where('delivery_state', 'completed')->count())->toBe(1);
});

test('queue outbox reclaims an expired delivery claim', function () {
    DB::table('jobs')->delete();
    DB::table('queue_outboxes')->delete();
    $id = app(AfterCommitQueueOutbox::class)->store('{"job":"fixture"}', 'default');
    DB::table('queue_outboxes')->where('id', $id)->update([
        'claimed_at' => now()->subMinutes(10),
        'delivery_state' => 'delivering',
    ]);

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('queue_outboxes')->where('id', $id)->value('delivery_state'))->toBe('completed');
});

test('queue outbox does not retry a recent delivery claim', function () {
    DB::table('jobs')->delete();
    DB::table('queue_outboxes')->delete();
    $id = app(AfterCommitQueueOutbox::class)->store('{"job":"fixture"}', 'default');
    DB::table('queue_outboxes')->where('id', $id)->update([
        'claimed_at' => now(),
        'delivery_state' => 'delivering',
    ]);

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('queue_outboxes')->where('id', $id)->value('delivery_state'))->toBe('delivering');
});

test('queue outbox completion cannot overwrite a reclaimed claim', function () {
    DB::table('jobs')->delete();
    DB::table('queue_outboxes')->delete();
    config(['queue.connections.failover.connections' => ['database']]);
    $backend = Mockery::mock();
    $backend->shouldReceive('pushRaw')->once()->andReturnUsing(function (): string {
        $row = DB::table('queue_outboxes')->orderByDesc('id')->first();
        DB::table('queue_outboxes')->where('id', $row->id)->update([
            'claim_token' => 'new-owner',
            'claimed_at' => now()->addMinutes(5),
        ]);

        return 'job-id';
    });
    Queue::shouldReceive('connection')->with('database')->once()->andReturn($backend);
    $id = app(AfterCommitQueueOutbox::class)->store('{"job":"fixture"}', 'default');

    expect(app(AfterCommitQueueOutbox::class)->recover())->toBe(0)
        ->and(DB::table('queue_outboxes')->where('id', $id)->value('delivery_state'))->toBe('delivering')
        ->and(DB::table('queue_outboxes')->where('id', $id)->value('claim_token'))->toBe('new-owner');
});
