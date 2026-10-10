<?php

declare(strict_types=1);

use App\Agovena\Support\CreateTicket;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Tickets\Index as TicketIndex;
use App\Livewire\Admin\Tickets\Show as TicketShow;
use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('ticket list keeps every ticket field visible in a compact responsive row', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ticket Customer']);
    $staff = $this->createStaff();
    $ticket = app(CreateTicket::class)->handle($customer, 'A broken checkout', 'Please help.');
    $ticket->update(['staff_user_id' => $staff->id]);

    Livewire::actingAs($staff)->test(TicketIndex::class)
        ->assertSee('c-ticket-list__table', false)
        ->assertSee('c-ticket-list__identity', false)
        ->assertSee('data-label="'.__('common.status').'"', false)
        ->assertSee('data-label="'.__('admin.tickets.assignee').'"', false)
        ->assertSee('<a class="c-ticket-list__number" href="'.route('admin.tickets.show', $ticket).'">'.$ticket->number.'</a>', false)
        ->assertSee($ticket->number)
        ->assertSee('A broken checkout')
        ->assertSee('Ticket Customer')
        ->assertSee($staff->name)
        ->assertSee(__('admin.tickets.status.open'));
});

test('ticket list status filter retains its existing Livewire behavior', function (): void {
    $customer = Customer::factory()->create();
    $open = app(CreateTicket::class)->handle($customer, 'Open ticket', 'Open message');
    $closed = app(CreateTicket::class)->handle($customer, 'Closed ticket', 'Closed message');
    $closed->update(['status' => TicketStatus::Closed]);

    Livewire::actingAs($this->createStaff())->test(TicketIndex::class)
        ->set('status', TicketStatus::Open->value)
        ->assertSee($open->number)
        ->assertDontSee($closed->number);
});

test('ticket detail keeps message and private attachment links alongside the reply workflow', function (): void {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $ticket = app(CreateTicket::class)->handle(
        $customer,
        'Document review',
        'Please review this receipt.',
        attachments: [UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')],
    );
    $attachment = $ticket->messages()->firstOrFail()->attachments()->firstOrFail();

    Livewire::actingAs($this->createStaff())->test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('c-ticket-workspace__layout', false)
        ->assertSee('c-ticket-workspace__conversation', false)
        ->assertSee('c-ticket-workspace__reply-actions', false)
        ->assertSee('Please review this receipt.')
        ->assertSee(route('admin.ticket-attachments.download', $attachment), false)
        ->assertSee('receipt.pdf')
        ->assertSee('wire:submit="updateStatus"', false)
        ->assertSee('wire:submit="sendReply"', false)
        ->assertSee('wire:model="is_internal"', false)
        ->assertSee('wire:model="attachments"', false)
        ->assertSee('wire:click="assignSelf"', false);
});

test('ticket view-only staff can read the workspace but cannot see or invoke management actions', function (): void {
    $ticket = app(CreateTicket::class)->handle(Customer::factory()->create(), 'View only', 'Visible customer message');
    $staff = $this->createStaff([], ['tickets.view']);

    Livewire::actingAs($staff)->test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Visible customer message')
        ->assertSee('c-ticket-workspace__conversation', false)
        ->assertDontSee('wire:submit="updateStatus"', false)
        ->assertDontSee('wire:submit="sendReply"', false)
        ->assertDontSee('wire:click="assignSelf"', false)
        ->call('assignSelf')->assertForbidden();

    Livewire::actingAs($staff)->test(TicketShow::class, ['ticket' => $ticket])
        ->call('updateStatus')->assertForbidden();

    Livewire::actingAs($staff)->test(TicketShow::class, ['ticket' => $ticket])
        ->call('sendReply')->assertForbidden();
});
