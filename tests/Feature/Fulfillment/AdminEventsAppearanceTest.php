<?php

declare(strict_types=1);

use Agovena\Modules\Events\Enums\EventStatus;
use Agovena\Modules\Events\Enums\EventTicketStatus;
use Agovena\Modules\Events\Http\Livewire\Admin\CheckIn;
use Agovena\Modules\Events\Http\Livewire\Admin\EventShow;
use Agovena\Modules\Events\Http\Livewire\Admin\EventsIndex;
use Agovena\Modules\Events\Models\Event;
use Agovena\Modules\Events\Models\EventPerformance;
use Agovena\Modules\Events\Models\EventTicket;
use Agovena\Modules\Events\Models\EventTicketType;
use App\Models\Order;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

beforeEach(function (): void {
    enableFirstPartyModules(['events']);
});

test('view-only staff see event performances and ticket products without editing forms', function () {
    $event = Event::query()->create([
        'name' => 'Read-only concert',
        'slug' => 'read-only-concert',
        'venue' => 'Main Hall',
        'description' => 'An evening performance',
        'status' => EventStatus::Published,
    ]);
    $performance = EventPerformance::query()->create([
        'event_id' => $event->id,
        'starts_at' => now()->addWeek(),
        'capacity' => 120,
        'venue' => 'Main Hall',
    ]);
    $product = Product::factory()->create(['name' => 'Balcony seat']);
    EventTicketType::query()->create([
        'event_id' => $event->id,
        'performance_id' => $performance->id,
        'product_id' => $product->id,
        'name' => 'Balcony',
    ]);

    $html = Livewire::actingAs($this->createStaff(permissions: ['events.view']))
        ->test(EventShow::class, ['event' => $event])->html();

    expect($html)->toContain(__('events::admin.performances'))
        ->toContain(__('events::admin.ticket_types'))
        ->toContain('Balcony seat')
        ->toContain('Balcony')
        ->toContain('120')
        ->toContain('An evening performance')
        ->toContain(__('events::admin.status.published'))
        ->not->toContain('wire:submit="save"')
        ->not->toContain('wire:submit="addPerformance"')
        ->not->toContain('wire:submit="attachTicketType"');
});

test('event list preserves all statuses and twenty-per-page results with a readable row', function (): void {
    foreach (['draft', 'published', 'cancelled'] as $status) {
        Event::query()->create([
            'name' => ucfirst($status).' concert',
            'slug' => $status.'-concert',
            'status' => EventStatus::from($status),
        ]);
    }
    foreach (range(1, 18) as $number) {
        Event::query()->create([
            'name' => 'Extra event '.$number,
            'slug' => 'extra-event-'.$number,
            'status' => EventStatus::Draft,
        ]);
    }

    $page = Livewire::actingAs($this->createStaff(permissions: ['events.view']))->test(EventsIndex::class);
    $html = $page->html();

    expect($html)->toContain('c-events__table')
        ->toContain('data-label="'.__('common.status').'"')
        ->toContain('data-label="'.__('events::admin.performances').'"')
        ->toContain('ag-badge--success')
        ->toContain('ag-badge--warning')
        ->toContain('ag-badge--muted')
        ->toContain(route('admin.events.show', Event::query()->where('slug', 'published-concert')->firstOrFail()))
        ->not->toContain('wire:submit="create"')
        ->not->toContain('Draft concert');
    expect(substr_count($html, '<td class="c-events__identity">'))->toBe(20);

    $page->set('paginators.page', 2)->assertSee('Draft concert');
});

test('managers retain the create form and all detail fields and actions', function (): void {
    $staff = $this->createStaff();
    $event = Event::query()->create([
        'name' => 'Editable concert',
        'slug' => 'editable-concert',
        'status' => EventStatus::Draft,
    ]);

    Livewire::actingAs($staff)->test(EventsIndex::class)
        ->assertSee('wire:submit="create"', false)
        ->assertSee('wire:model="name"', false)
        ->assertSee('wire:model="venue"', false);
    $detail = Livewire::actingAs($staff)->test(EventShow::class, ['event' => $event]);
    foreach (['save', 'addPerformance', 'attachTicketType'] as $action) {
        $detail->assertSee('wire:submit="'.$action.'"', false);
    }
    foreach (['name', 'venue', 'description', 'status', 'performance_starts_at', 'performance_capacity', 'ticket_product_id', 'ticket_performance_id', 'ticket_name'] as $field) {
        $detail->assertSee('wire:model'.(in_array($field, ['performance_capacity', 'ticket_product_id', 'ticket_performance_id'], true) ? '.number' : '').'="'.$field.'"', false);
    }
    foreach (['draft', 'published', 'cancelled'] as $status) {
        $detail->assertSee('value="'.$status.'"', false);
    }
    $detail->assertSee(__('events::admin.no_performances'))
        ->assertSee(__('events::admin.no_ticket_types'));
    expect($detail->html())->toContain('c-events__section')->toContain('ag-empty');
});

test('event screens place every section on a labelled shared Admin card', function (): void {
    $staff = $this->createStaff();
    $event = Event::query()->create(['name' => 'Card concert', 'slug' => 'card-concert', 'status' => EventStatus::Draft]);

    $index = Livewire::actingAs($staff)->test(EventsIndex::class)->html();
    $detail = Livewire::actingAs($staff)->test(EventShow::class, ['event' => $event])->html();
    $checkIn = Livewire::actingAs($staff)->test(CheckIn::class)->html();

    expect($index)->toContain('class="ag-card c-events__section" aria-labelledby="event-create-heading"')
        ->toContain('id="event-create-heading" class="ag-card__title"')
        ->and($detail)->toContain('aria-labelledby="event-details-heading"')
        ->toContain('aria-labelledby="event-performances-heading"')
        ->toContain('aria-labelledby="event-ticket-types-heading"')
        ->toContain('class="ag-card__header c-events__heading"')
        ->and($checkIn)->toContain('class="ag-card c-events__section c-events__scan" aria-labelledby="event-checkin-heading"');

    foreach ([$index, $detail, $checkIn] as $html) {
        expect($html)->not->toContain('admin-panel');
    }
});

test('check-in keeps its idle form and validation feedback in both locales', function (string $locale): void {
    app()->setLocale($locale);
    $page = Livewire::actingAs($this->createStaff(permissions: ['events.checkin']))->test(CheckIn::class);

    expect($page->html())->toContain('c-events--check-in')
        ->toContain(__('events::admin.checkin_title'))
        ->toContain(__('events::admin.code'))
        ->toContain('wire:submit="submit"')
        ->not->toContain('c-events__result');
    $page->call('submit')->assertHasErrors(['code' => 'required']);
    expect($page->html())->toContain('ag-field__error');
})->with(['en', 'nl']);

test('check-in presents already-used ticket facts without running a scan', function (): void {
    $event = Event::query()->create(['name' => 'Admission event', 'slug' => 'admission-event', 'status' => EventStatus::Published]);
    $performance = EventPerformance::query()->create(['event_id' => $event->id, 'starts_at' => now()->addDay(), 'capacity' => 25]);
    $product = Product::factory()->create();
    $type = EventTicketType::query()->create(['event_id' => $event->id, 'performance_id' => $performance->id, 'product_id' => $product->id, 'name' => 'Standard']);
    $ticket = EventTicket::query()->create([
        'number' => 'TEST-EVENT-001',
        'token' => str_repeat('a', 64),
        'event_id' => $event->id,
        'performance_id' => $performance->id,
        'ticket_type_id' => $type->id,
        'product_id' => $product->id,
        'order_id' => Order::factory()->create()->id,
        'customer_email' => 'test@example.test',
        'customer_name' => 'Example Guest',
        'status' => EventTicketStatus::CheckedIn,
        'checked_in_at' => now(),
    ]);

    $page = Livewire::actingAs($this->createStaff(permissions: ['events.checkin']))
        ->test(CheckIn::class)
        ->set('lastTicketId', $ticket->id)
        ->set('already', true);
    expect($page->html())->toContain('c-events__result')
        ->toContain(__('events::admin.already_checked_in'))
        ->toContain('TEST-EVENT-001')
        ->toContain('Admission event')
        ->toContain('Example Guest')
        ->toContain('ag-alert--warning');

    $page->set('already', false)
        ->assertSee(__('events::admin.checkin_ok'))
        ->assertSee('ag-alert--success', false)
        ->assertSee('TEST-EVENT-001');
});

test('event list and read-only detail use the active English and Dutch catalogues', function (string $locale): void {
    app()->setLocale($locale);
    $event = Event::query()->create([
        'name' => 'Localized concert',
        'slug' => 'localized-concert',
        'status' => EventStatus::Cancelled,
    ]);
    $staff = $this->createStaff(permissions: ['events.view']);

    $index = Livewire::actingAs($staff)->test(EventsIndex::class);
    $detail = Livewire::actingAs($staff)->test(EventShow::class, ['event' => $event]);
    expect($index->html())->toContain(__('events::admin.title'))
        ->toContain(__('events::admin.status.cancelled'))
        ->toContain(__('events::admin.performances'))
        ->not->toContain('events::admin.');
    expect($detail->html())->toContain(__('events::admin.details'))
        ->toContain(__('events::admin.status.cancelled'))
        ->toContain(__('events::admin.no_performances'))
        ->toContain(__('events::admin.no_ticket_types'))
        ->not->toContain('events::admin.');
})->with(['en', 'nl']);

test('empty event list remains navigable without a management form', function (): void {
    $html = Livewire::actingAs($this->createStaff(permissions: ['events.view']))->test(EventsIndex::class)->html();

    expect($html)->toContain(__('events::admin.empty'))
        ->toContain('ag-empty')
        ->not->toContain('c-events__table')
        ->not->toContain('wire:submit="create"');
});

test('event detail and create forms show every reachable validation error beside its field', function (): void {
    $staff = $this->createStaff();
    $event = Event::query()->create(['name' => 'Validated concert', 'slug' => 'validated-concert', 'status' => EventStatus::Draft]);

    $detail = Livewire::actingAs($staff)->test(EventShow::class, ['event' => $event])
        ->set('venue', str_repeat('v', 256))
        ->set('description', str_repeat('d', 5001))
        ->call('save')
        ->assertHasErrors(['venue', 'description']);
    $detail->set('performance_starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
        ->set('performance_capacity', 0)
        ->call('addPerformance')
        ->assertHasErrors(['performance_capacity']);

    $index = Livewire::actingAs($staff)->test(EventsIndex::class)
        ->set('name', 'Too long venue')
        ->set('venue', str_repeat('v', 256))
        ->call('create')
        ->assertHasErrors(['venue']);

    expect($detail->html())->toMatch('/id="perf-cap"(?:(?!<\/div>).)*class="ag-field__error"/s')
        ->and($index->html())->toMatch('/id="event-venue"(?:(?!<\/div>).)*class="ag-field__error"/s');

    $detailErrors = Livewire::actingAs($staff)->test(EventShow::class, ['event' => $event])
        ->set('venue', str_repeat('v', 256))
        ->set('description', str_repeat('d', 5001))
        ->call('save')
        ->html();
    expect($detailErrors)->toMatch('/id="ev-venue"(?:(?!<\/div>).)*class="ag-field__error"/s')
        ->toMatch('/id="ev-description"(?:(?!<\/div>).)*class="ag-field__error"/s');
});
