<?php

declare(strict_types=1);

use App\Listeners\LogFailedNotification;
use App\Livewire\Admin\Notifications\EmailLogIndex;
use App\Models\EmailLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('email log presents sent and failed rows as labeled responsive records', function () {
    $viewer = $this->createStaff(permissions: ['notifications.view']);
    EmailLog::query()->create(['to' => 'first@example.invalid', 'subject' => 'First subject', 'status' => 'sent', 'created_at' => now()->subMinute()]);
    EmailLog::query()->create(['to' => 'second@example.invalid', 'subject' => null, 'status' => 'failed', 'error' => 'Delivery failed', 'created_at' => now()]);

    $html = $this->actingAs($viewer)->get('/admin/email-log')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $xpath = new DOMXPath($document);
    $table = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " ag-table--mobile-cards ")]')->item(0);

    expect($table)->not->toBeNull()
        ->and($xpath->query('.//tbody/tr', $table))->toHaveCount(2)
        ->and($xpath->query('.//caption', $table))->toHaveCount(1)
        ->and($xpath->query('.//tbody/tr[1]/td[@data-label]', $table))->toHaveCount(4)
        ->and($xpath->query('.//tbody/tr[1]//span[contains(@class, "ag-badge--danger")]', $table))->toHaveCount(1)
        ->and($xpath->query('.//tbody/tr[2]//span[contains(@class, "ag-badge--success")]', $table))->toHaveCount(1)
        ->and($html)->toContain(__('admin.email_log.error_hidden'))
        ->toContain('first@example.invalid');

    foreach (['en' => 'Delivery details hidden.', 'nl' => 'Leveringsdetails verborgen.'] as $locale => $expected) {
        app()->setLocale($locale);
        $page = $this->actingAs($viewer)->get('/admin/email-log')->assertOk()->getContent();
        expect($page)->toContain($expected);
    }
});

test('email log preserves paginated order without exposing stored transport errors', function () {
    $viewer = $this->createStaff(permissions: ['notifications.view']);
    EmailLog::query()->create([
        'to' => 'safe@example.invalid',
        'subject' => '<script>QA subject</script>',
        'status' => 'failed',
        'error' => 'SMTP rejected: password=qa-private-sentinel <img src=x onerror=alert(1)>',
        'created_at' => now()->subDay(),
    ]);
    foreach (range(1, 30) as $number) {
        EmailLog::query()->create(['to' => "bulk-{$number}@example.invalid", 'subject' => "Bulk {$number}", 'status' => 'sent', 'created_at' => now()]);
    }

    $first = $this->actingAs($viewer)->get('/admin/email-log')->assertOk()->getContent();
    $last = $this->actingAs($viewer)->get('/admin/email-log?page=2')->assertOk()->getContent();
    expect(substr_count($first, 'wire:key="email-log-'))->toBe(30)
        ->and(substr_count($last, 'wire:key="email-log-'))->toBe(1)
        ->and($first)->not->toContain('safe@example.invalid')
        ->and($last)->toContain('safe@example.invalid')
        ->toContain('&lt;script&gt;QA subject&lt;/script&gt;')
        ->toContain(__('admin.email_log.error_hidden'))
        ->not->toContain('qa-private-sentinel')
        ->not->toContain('password=')
        ->not->toContain('<script>QA subject</script>')
        ->not->toContain('<img src=x onerror=alert(1)>');
});

test('an out-of-range email log page offers a way back without claiming the log is empty', function () {
    $viewer = $this->createStaff(permissions: ['notifications.view']);
    EmailLog::query()->create(['to' => 'present@example.invalid', 'subject' => 'Present', 'status' => 'sent']);

    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        $html = $this->actingAs($viewer)->get('/admin/email-log?page=99')->assertOk()->getContent();
        expect($html)->toContain(__('admin.email_log.empty_page'))
            ->toContain(__('admin.email_log.first_page'))
            ->not->toContain(__('admin.email_log.empty_text'));
    }

    Livewire::actingAs($viewer)->test(EmailLogIndex::class)
        ->call('gotoPage', 99)
        ->assertSee(__('admin.email_log.empty_page'))
        ->call('resetPage')
        ->assertSee('present@example.invalid');
});

test('staff without notifications view cannot access the email log', function () {
    $staff = $this->createStaff(permissions: ['settings.view']);
    $this->actingAs($staff)->get('/admin/email-log')->assertForbidden();
});

test('failed mail logs never persist exception or transport message contents', function () {
    $notifiable = new class
    {
        public function routeNotificationFor(string $channel): string
        {
            return 'qa@example.invalid';
        }
    };
    $notification = new class extends Notification {};
    $listener = app(LogFailedNotification::class);

    foreach ([
        ['exception' => new RuntimeException('qa-private-exception-marker')],
        ['message' => 'qa-private-message-marker'],
    ] as $data) {
        $listener->handle(new NotificationFailed($notifiable, $notification, 'mail', $data));
    }

    expect(EmailLog::query()->orderBy('id')->pluck('error')->all())
        ->toBe(['Mail delivery failed.', 'Mail delivery failed.']);
});

test('email log retains one flash status and a localized empty state', function () {
    $viewer = $this->createStaff(permissions: ['notifications.view']);
    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        $html = $this->actingAs($viewer)->withSession(['status' => 'QA status'])->get('/admin/email-log')->assertOk()->getContent();
        expect(substr_count($html, 'QA status'))->toBe(1)
            ->and($html)->toContain(__('admin.email_log.empty'))
            ->toContain(__('admin.email_log.empty_text'));
    }
});
