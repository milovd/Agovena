<?php

declare(strict_types=1);

use App\Agovena\Mail\ApplyMailSettings;
use App\Agovena\Notifications\RendersNotificationMail;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Notifications\Templates;
use App\Livewire\Admin\System\FailedJobs;
use App\Models\EmailLog;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Notifications\CreditNoteIssuedNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\OrderPlaced;
use App\Notifications\PaymentRecordedNotification;
use App\Notifications\RefundProcessedNotification;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('custom notification templates interpolate allowlisted placeholders only', function () {
    NotificationTemplate::query()->create([
        'key' => 'order_placed',
        'subject' => 'Thanks {{name}} {{ $secret }} @php echo "pwn" @endphp',
        'body' => "Hello {{name}}\n\nOrder {{number}} {!! \$x !!} {{ unknown }}",
        'enabled' => true,
    ]);

    $mail = app(RendersNotificationMail::class)->mail('order_placed', [
        'name' => 'Jane',
        'number' => 'AGO-1',
        'total' => '€10.00',
        'action_url' => 'https://example.test/orders/1',
        'action_label' => 'View',
    ]);

    $body = implode("\n", $mail->introLines);

    expect($mail->subject)->toBe('Thanks Jane')
        ->and($body)->toContain('Hello Jane')
        ->and($body)->toContain('Order AGO-1')
        ->and($mail->subject)->not->toContain('pwn')
        ->and($body)->not->toContain('pwn')
        ->and($body)->not->toContain('pwn')
        ->and($body)->not->toContain('$secret')
        ->and($body)->not->toContain('$x')
        ->and($body)->not->toContain('unknown');
});

test('disabled notification templates send on no mail channel', function () {
    NotificationTemplate::query()->create([
        'key' => 'order_placed',
        'subject' => 'Muted',
        'body' => 'Muted',
        'enabled' => false,
    ]);

    $order = Order::factory()->create();

    expect((new OrderPlaced($order))->via((object) []))->toBe([]);
});

test('merchant from address is applied from mail settings', function () {
    app(SettingsRepository::class)->set('mail', 'from_address', 'shop@example.test');
    app(SettingsRepository::class)->set('mail', 'from_name', 'Example Shop');
    app(ApplyMailSettings::class)();

    expect(config('mail.from.address'))->toBe('shop@example.test')
        ->and(config('mail.from.name'))->toBe('Example Shop');
});

test('sent mail is recorded in the email log', function () {
    Mail::raw('Hello from Agovena', static function ($message): void {
        $message->to('buyer@example.test')->subject('Order ping');
    });

    expect(EmailLog::query()->where('to', 'buyer@example.test')->where('status', 'sent')->value('subject'))
        ->toBe('Order ping');
});

test('owner can save a notification template', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Templates::class)
        ->call('select', 'invoice_issued')
        ->set('subject', 'Invoice {{number}}')
        ->set('body', 'Hello {{name}}')
        ->set('enabled', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('admin.notifications.saved'), false);

    $this->assertDatabaseHas('notification_templates', [
        'key' => 'invoice_issued',
        'subject' => 'Invoice {{number}}',
        'enabled' => 1,
    ]);
});

test('failed jobs admin page renders empty state', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.failed-jobs'))
        ->assertOk()
        ->assertSee(__('admin.failed_jobs.empty'), false)
        ->assertDontSee('SQLSTATE', false);

    Livewire::actingAs($staff)
        ->test(FailedJobs::class)
        ->assertOk()
        ->assertSee(__('admin.failed_jobs.empty_text'), false);
});

test('commerce notifications are queued for async delivery', function () {
    $queued = [
        OrderPlaced::class,
        InvoiceIssuedNotification::class,
        CreditNoteIssuedNotification::class,
        PaymentRecordedNotification::class,
        RefundProcessedNotification::class,
        SubscriptionCancelledNotification::class,
        TicketRepliedNotification::class,
    ];

    foreach ($queued as $class) {
        expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue($class);
    }
});

test('scheduler registers heartbeat renewals provisioning and unpaid cancel', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('process-subscription-renewals')
        ->expectsOutputToContain('sync-provisioning')
        ->expectsOutputToContain('cancel-stale-unpaid-orders')
        ->expectsOutputToContain('prune-logs')
        ->assertSuccessful();
});
