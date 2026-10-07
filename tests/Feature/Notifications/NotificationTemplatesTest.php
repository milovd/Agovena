<?php

declare(strict_types=1);

use App\Agovena\Mail\ApplyMailSettings;
use App\Agovena\Notifications\RendersNotificationMail;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Notifications\Index;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('notification templates use a list and separate form routes', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/notifications')
        ->assertOk()
        ->assertSee(__('admin.notifications.title'))
        ->assertSee('/admin/notifications/create', false)
        ->assertDontSee('tpl-body', false);

    $this->actingAs($staff)
        ->get('/admin/notifications/create')
        ->assertOk()
        ->assertSee(__('admin.notifications.create_title'))
        ->assertSee('tpl-body', false);

    $this->actingAs($staff)
        ->get('/admin/notifications/order_placed/edit')
        ->assertOk()
        ->assertSee(__('admin.notifications.edit_title'))
        ->assertSee('tpl-body', false);
});

test('notification templates can remove a custom override and restore defaults', function () {
    $staff = $this->createStaff();
    NotificationTemplate::query()->create([
        'key' => 'order_placed',
        'subject' => 'Custom subject',
        'body' => 'Custom body',
        'enabled' => true,
    ]);

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('confirmRemove', 'order_placed')
        ->call('remove')
        ->assertHasNoErrors();

    expect(NotificationTemplate::query()->where('key', 'order_placed')->exists())->toBeFalse();
});

test('notification template editor keeps guidance compact', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Templates::class)
        ->assertDontSee(__('admin.notifications.mail_help'), false)
        ->assertDontSee(__('admin.notifications.notification_help'), false)
        ->assertDontSee(__('admin.notifications.placeholders_help'), false);
});

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

test('owner can configure mail format channel states and customer choice', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Templates::class)
        ->call('select', 'invoice_issued')
        ->set('mailFormat', 'html')
        ->set('body', '<p>Hello {{name}}</p><img src="https://example.test/logo.png" alt="Logo">')
        ->set('mailEnabled', false)
        ->set('notificationEnabled', true)
        ->set('notificationTitle', 'Invoice {{number}} ready')
        ->set('notificationBody', 'Open {{number}} from {{name}}.')
        ->set('pushEnabled', false)
        ->set('userChoice', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('notification_templates', [
        'key' => 'invoice_issued',
        'mail_format' => 'html',
        'notification_title' => 'Invoice {{number}} ready',
        'notification_body' => 'Open {{number}} from {{name}}.',
        'mail_enabled' => 0,
        'in_app_enabled' => 1,
        'push_enabled' => 0,
        'user_choice' => 1,
    ]);
});

test('html notification templates render a sanitized mail view', function () {
    NotificationTemplate::query()->create([
        'key' => 'order_placed',
        'subject' => 'Order {{number}}',
        'body' => '<p>Hello {{name}}</p><img src="https://example.test/logo.png"><script>alert(1)</script>',
        'enabled' => true,
        'mail_format' => 'html',
        'mail_enabled' => true,
        'in_app_enabled' => true,
        'push_enabled' => true,
        'user_choice' => false,
    ]);

    $mail = app(RendersNotificationMail::class)->mail('order_placed', [
        'name' => 'Jane',
        'number' => 'AGO-1',
        'total' => '€10.00',
        'action_url' => 'https://example.test/orders/1',
        'action_label' => 'View',
    ]);

    expect($mail->view)->toBe('mail.notification')
        ->and($mail->viewData['bodyHtml'])->toContain('<img')
        ->and($mail->viewData['bodyHtml'])->not->toContain('<script');
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

test('scheduler uses the configured backup interval', function () {
    Cache::forget('agovena.settings.backups.interval');
    config()->set('agovena.backups.interval', 'hourly');

    $this->artisan('schedule:list')
        ->expectsOutputToContain('0 * * * *  php artisan agovena:backup')
        ->assertSuccessful();
});
