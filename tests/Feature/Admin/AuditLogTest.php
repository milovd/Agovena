<?php

declare(strict_types=1);

use App\Agovena\Audit\AuditLogExporter;
use App\Agovena\Audit\AuditLogger;
use App\Livewire\Admin\Audit\Index;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('structured audit entries capture context and redact sensitive values', function (): void {
    $staff = $this->createStaff();
    $order = Order::factory()->create();
    $this->actingAs($staff);

    $request = Request::create('/admin/orders/'.$order->id, 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.15',
        'HTTP_USER_AGENT' => 'Audit test browser',
    ]);
    $request->headers->set('X-Request-ID', 'request-123');
    $request->headers->set('X-Correlation-ID', 'correlation-456');
    app()->instance('request', $request);

    $log = app(AuditLogger::class)->log(
        'payment.reviewed',
        $order,
        [
            'payment_id' => 42,
            'provider_reference' => 'txn_123',
            'api_token' => 'do-not-store',
            'customer_email' => 'private@example.test',
            'nested' => ['authorization' => 'Bearer do-not-store'],
        ],
        ['status' => 'pending', 'secret' => 'old-secret'],
        ['status' => 'paid', 'amount' => 2500],
        'success',
    );

    expect($log->event_id)->not->toBeNull()
        ->and($log->category)->toBe('payment')
        ->and($log->severity)->toBe('warning')
        ->and($log->outcome)->toBe('success')
        ->and($log->actor_type)->toBe('staff')
        ->and($log->actor_id)->toBe($staff->id)
        ->and($log->subject_id)->toBe($order->id)
        ->and($log->request_id)->toBe('request-123')
        ->and($log->correlation_id)->toBe('correlation-456')
        ->and($log->ip)->toBe('203.0.113.15')
        ->and($log->properties['api_token'])->toBe('[REDACTED]')
        ->and($log->properties['customer_email'])->toBe('[REDACTED]')
        ->and($log->properties['nested']['authorization'])->toBe('[REDACTED]')
        ->and($log->before['secret'])->toBe('[REDACTED]')
        ->and($log->after['status'])->toBe('paid')
        ->and($log->integrityIsValid())->toBeTrue();
});

test('failed and pending action names receive useful default outcomes', function (): void {
    $failed = app(AuditLogger::class)->log('refund.failed');
    $pending = app(AuditLogger::class)->log('payment.pending');
    $denied = app(AuditLogger::class)->log('security.access_denied');

    expect($failed->outcome)->toBe('failure')
        ->and($failed->severity)->toBe('warning')
        ->and($pending->outcome)->toBe('pending')
        ->and($denied->outcome)->toBe('denied')
        ->and($denied->severity)->toBe('critical');
});

test('audit UI uses simple filters and opens a dedicated detail page', function (): void {
    $staff = $this->createStaff();
    $this->actingAs($staff);

    $payment = app(AuditLogger::class)->log('payment.captured', null, [
        'provider_reference' => 'txn_export',
        'secret' => 'must-not-export',
    ]);
    app(AuditLogger::class)->log('order.viewed', null, ['order_id' => 99]);

    Livewire::test(Index::class)
        ->set('category', 'payment')
        ->assertSee('payment.captured')
        ->assertSee('audit-log__stack', false)
        ->assertDontSee('order.viewed')
        ->assertDontSee(__('admin.audit.advanced_filters'))
        ->assertSee(route('admin.audit.show', $payment));

    $detail = $this->get(route('admin.audit.show', $payment));

    $detail->assertOk()
        ->assertSee('payment.captured')
        ->assertSee('txn_export')
        ->assertSee('REDACTED')
        ->assertDontSee('must-not-export');

    $response = app(AuditLogExporter::class)->download(['category' => 'payment']);
    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain('payment.captured')
        ->and($csv)->toContain('[REDACTED]')
        ->and($csv)->not->toContain('must-not-export')
        ->and($csv)->not->toContain('order.viewed');

    expect(fn () => $payment->update(['action' => 'tampered']))
        ->toThrow(LogicException::class);
});

test('audit query supports correlation and subject lookup', function (): void {
    $staff = $this->createStaff();
    $order = Order::factory()->create();
    $this->actingAs($staff);
    $request = Request::create('/admin/orders', 'GET');
    $request->headers->set('X-Correlation-ID', 'case-789');
    app()->instance('request', $request);

    $log = app(AuditLogger::class)->log('order.investigated', $order, ['order_id' => $order->id]);

    Livewire::test(Index::class)
        ->set('correlationId', 'case-789')
        ->assertSee('order.investigated')
        ->set('subjectId', (string) $order->id)
        ->assertSee('order.investigated');
});

test('retention command prunes expired audit entries without removing recent entries', function (): void {
    $old = app(AuditLogger::class)->log('order.expired');
    $recent = app(AuditLogger::class)->log('order.recent');

    DB::table('audit_logs')->where('id', $old->id)->update([
        'created_at' => now()->subDays(366),
    ]);

    Artisan::call('agovena:prune-logs');

    expect(DB::table('audit_logs')->where('id', $old->id)->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->where('id', $recent->id)->exists())->toBeTrue();
});
