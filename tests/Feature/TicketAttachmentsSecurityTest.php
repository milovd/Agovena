<?php

declare(strict_types=1);

use App\Agovena\Support\CreateTicket;
use App\Agovena\Support\ReplyToTicket;
use App\Agovena\Support\StoreTicketMessageAttachments;
use App\Agovena\Support\TicketAttachmentPolicy;
use App\Models\Customer;
use App\Models\TicketMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
});

test('ticket attachments store on private local disk never public', function () {
    $customer = Customer::factory()->create();
    $upload = UploadedFile::fake()->image('screenshot.png', 40, 40);

    $ticket = app(CreateTicket::class)->handle(
        $customer,
        'With file',
        'Please see screenshot.',
        attachments: [$upload],
    );

    $attachment = TicketMessageAttachment::query()->first();
    expect($attachment)->not->toBeNull()
        ->and($attachment->disk)->toBe(TicketAttachmentPolicy::DISK)
        ->and($attachment->extension)->toBe('png')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(str_contains($attachment->path, 'tickets/'.$ticket->id.'/'))->toBeTrue()
        ->and(str_ends_with($attachment->path, '.php'))->toBeFalse();
});

test('ticket attachment upload rejects php webshells', function () {
    $customer = Customer::factory()->create();
    $upload = UploadedFile::fake()->create('shell.php', 120, 'application/x-php');

    expect(fn () => app(CreateTicket::class)->handle(
        $customer,
        'Exploit',
        'Should fail.',
        attachments: [$upload],
    ))->toThrow(ValidationException::class);

    expect(TicketMessageAttachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('ticket attachment upload rejects svg', function () {
    $customer = Customer::factory()->create();
    $upload = UploadedFile::fake()->create('xss.svg', 80, 'image/svg+xml');

    expect(fn () => app(CreateTicket::class)->handle(
        $customer,
        'SVG',
        'Should fail.',
        attachments: [$upload],
    ))->toThrow(ValidationException::class);

    expect(TicketMessageAttachment::query()->count())->toBe(0);
});

test('owner can download attachment with nosniff force download headers', function () {
    $customer = Customer::factory()->create();
    $upload = UploadedFile::fake()->image('proof.jpg', 32, 32);
    $ticket = app(CreateTicket::class)->handle($customer, 'Proof', 'Body', attachments: [$upload]);
    $attachment = TicketMessageAttachment::query()->firstOrFail();

    $response = $this->actingAs($customer->user)
        ->get(route('customer.ticket-attachments.download', $attachment));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('private')->toContain('no-store')
        ->and(str_contains((string) $response->headers->get('Content-Disposition'), 'attachment'))->toBeTrue();
});

test('other customers cannot download ticket attachments', function () {
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();
    $upload = UploadedFile::fake()->image('secret.png', 20, 20);
    app(CreateTicket::class)->handle($owner, 'Private', 'Body', attachments: [$upload]);
    $attachment = TicketMessageAttachment::query()->firstOrFail();

    $this->actingAs($other->user)
        ->get(route('customer.ticket-attachments.download', $attachment))
        ->assertForbidden();
});

test('customers cannot download attachments on internal staff notes', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff();
    $ticket = app(CreateTicket::class)->handle($customer, 'Help', 'Visible');
    $upload = UploadedFile::fake()->create('note.pdf', 40, 'application/pdf');

    app(ReplyToTicket::class)->byStaff($ticket, $staff, 'Internal file note.', true, [$upload]);
    $attachment = TicketMessageAttachment::query()->firstOrFail();

    $this->actingAs($customer->user)
        ->get(route('customer.ticket-attachments.download', $attachment))
        ->assertNotFound();
});

test('staff can download customer ticket attachments', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff();
    $upload = UploadedFile::fake()->image('from-customer.png', 24, 24);
    app(CreateTicket::class)->handle($customer, 'Help', 'Body', attachments: [$upload]);
    $attachment = TicketMessageAttachment::query()->firstOrFail();

    $this->actingAs($staff)
        ->get(route('admin.ticket-attachments.download', $attachment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('store policy refuses more than max attachments', function () {
    $customer = Customer::factory()->create();
    $ticket = app(CreateTicket::class)->handle($customer, 'Many', 'Body');
    $message = $ticket->messages()->firstOrFail();
    $uploads = [];
    for ($i = 0; $i < TicketAttachmentPolicy::MAX_FILES + 1; $i++) {
        $uploads[] = UploadedFile::fake()->image("file{$i}.png", 10, 10);
    }

    expect(fn () => app(StoreTicketMessageAttachments::class)->handle($ticket, $message, $uploads))
        ->toThrow(ValidationException::class);
});
