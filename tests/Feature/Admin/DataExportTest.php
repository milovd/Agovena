<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Availability\AvailabilityMode;
use App\Agovena\Availability\Models\InventoryStock;
use App\Agovena\Exports\ExportDatasetRegistry;
use App\Agovena\Exports\ExportStreamWriter;
use App\Agovena\Exports\ExportValues;
use App\Agovena\Recurring\Enums\SubscriptionInterval;
use App\Agovena\Recurring\Enums\SubscriptionStatus;
use App\Agovena\Recurring\Models\Subscription;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Exports\Index as ExportsIndex;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

const DATA_EXPORT_ENTITY_PERMISSIONS = [
    'customers' => 'customers.view',
    'products' => 'products.view',
    'orders' => 'orders.view',
    'invoices' => 'invoices.view',
    'payments' => 'orders.view',
    'subscriptions' => 'subscriptions.view',
    'inventory' => 'inventory.view',
];

/**
 * One record per entity with recognisable values. Secrets are planted on the
 * same rows so tests can prove they never reach an export.
 *
 * @return array<string, string>
 */
function seedDataExportFixtures(): array
{
    $customer = Customer::factory()->create(['name' => 'Ada Export', 'email' => 'ada@export.test']);
    $customer->user->forceFill([
        'password' => 'hash-secret-PASSWORD-MARKER',
        'two_factor_secret' => 'TWO-FACTOR-SECRET-MARKER',
        'two_factor_recovery_codes' => 'RECOVERY-CODES-MARKER',
        'remember_token' => 'REMEMBER-TOKEN-MARKER',
    ])->saveQuietly();

    $product = Product::factory()->create([
        'name' => 'Export Widget',
        'sku' => 'SKU-EXPORT-1',
        'status' => ProductStatus::Active,
        'price_amount' => 1999,
        'currency' => 'EUR',
    ]);

    $order = Order::factory()->create([
        'number' => 'AGO-EXPORT-0001',
        'status' => OrderStatus::Paid,
        'customer_id' => $customer->id,
        'customer_name' => 'Ada Export',
        'customer_email' => 'ada@export.test',
        'subtotal_amount' => 1999,
        'total_amount' => 2419,
        'tax_amount' => 420,
        'currency' => 'EUR',
        'idempotency_key' => 'IDEMPOTENCY-KEY-MARKER',
        'idempotency_owner_hash' => 'IDEMPOTENCY-OWNER-MARKER',
    ]);
    $order->forceFill(['storefront_token' => 'STOREFRONT-TOKEN-MARKER'])->saveQuietly();

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 2419,
        'currency' => 'EUR',
        'method' => 'manual',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'reference' => 'PAY-REF-EXPORT',
    ]);
    $payment->forceFill([
        'reconciliation_status' => 'matched',
        'reconciliation_meta' => ['raw_payload' => 'PROVIDER-RAW-PAYLOAD-MARKER'],
    ])->saveQuietly();

    $invoice = Invoice::query()->create([
        'number' => 'INV-EXPORT-0001',
        'status' => InvoiceStatus::Paid,
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'customer_name' => 'Ada Export',
        'customer_email' => 'ada@export.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1999,
        'tax_amount' => 420,
        'total_amount' => 2419,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);

    CreditNote::query()->create([
        'number' => 'CN-EXPORT-0001',
        'status' => CreditNoteStatus::Issued,
        'invoice_id' => $invoice->id,
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'customer_name' => 'Ada Export',
        'customer_email' => 'ada@export.test',
        'issued_at' => now()->toDateString(),
        'reason' => 'Goodwill',
        'subtotal_amount' => 500,
        'tax_amount' => 105,
        'total_amount' => 605,
        'currency' => 'EUR',
    ]);

    Subscription::query()->create([
        'number' => 'SUB-EXPORT-0001',
        'customer_id' => $customer->id,
        'customer_email' => 'ada@export.test',
        'customer_name' => 'Ada Export',
        'product_id' => $product->id,
        'order_id' => $order->id,
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 1,
        'price_amount' => 1999,
        'currency' => 'EUR',
        'quantity' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'next_billing_at' => now()->addMonth(),
    ]);

    InventoryStock::query()->create([
        'product_id' => $product->id,
        'availability_mode' => AvailabilityMode::Finite,
        'quantity' => 42,
        'track_stock' => true,
        'allow_oversell' => false,
    ]);

    return [
        'customers' => 'ada@export.test',
        'products' => 'SKU-EXPORT-1',
        'orders' => 'AGO-EXPORT-0001',
        'invoices' => 'INV-EXPORT-0001',
        'payments' => 'PAY-REF-EXPORT',
        'subscriptions' => 'SUB-EXPORT-0001',
        'inventory' => 'SKU-EXPORT-1',
    ];
}

function downloadDataExport(TestResponse $response): string
{
    return (string) $response->streamedContent();
}

/** @return list<array<string, string>> */
function parseDataExportCsv(string $content): array
{
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    $content = substr($content, 3);
    $handle = fopen('php://memory', 'r+b');
    fwrite($handle, $content);
    rewind($handle);
    $header = fgetcsv($handle, null, ',', '"', '');
    $rows = [];
    while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
        if ($line === [null]) {
            continue;
        }
        $rows[] = array_combine($header, $line);
    }
    fclose($handle);

    return $rows;
}

dataset('data export entities', array_keys(DATA_EXPORT_ENTITY_PERMISSIONS));
dataset('data export formats', ['csv', 'json', 'xml']);

test('data.export is registered, labelled and synced onto the owner role', function () {
    $owner = $this->createStaff();

    expect(app(AdminRegistrar::class)->permissions())->toHaveKey('data.export')
        ->and(Role::findByName('owner', 'web')->hasPermissionTo('data.export'))->toBeTrue()
        ->and($owner->fresh()->can('data.export'))->toBeTrue()
        ->and(__('admin.permissions.data.export'))->toBe('Export data');

    app()->setLocale('nl');
    expect(__('admin.permissions.data.export'))->toBe('Gegevens exporteren')
        ->and(__('admin.exports.title'))->not->toBe('admin.exports.title');
});

test('exports has one system navigation item gated by data.export', function () {
    $byId = collect(app(AdminRegistrar::class)->navigationItems())->keyBy('id');

    expect($byId->get('exports')?->label)->toBe('admin.nav.exports')
        ->and($byId->get('exports')?->group)->toBe('admin.nav_groups.system')
        ->and($byId->get('exports')?->href)->toBe('/admin/exports')
        ->and($byId->get('exports')?->permission)->toBe('data.export');
});

test('the exports screen requires data.export', function () {
    $staff = $this->createStaff([], ['orders.view']);

    $this->actingAs($staff)->get('/admin/exports')->assertForbidden();
});

test('the exports screen only offers entities whose view permission is also held', function () {
    $staff = $this->createStaff([], ['data.export', 'orders.view']);

    $this->actingAs($staff)->get('/admin/exports')
        ->assertOk()
        ->assertSee(__('admin.exports.title'))
        ->assertSee(__('admin.exports.entities.orders'))
        ->assertSee(__('admin.exports.entities.payments'))
        ->assertDontSee(__('admin.exports.entities.customers'))
        ->assertDontSee(__('admin.exports.entities.inventory'))
        ->assertDontSeeText('admin.exports.');
});

test('the exports screen explains when no entity can be exported', function () {
    $staff = $this->createStaff([], ['data.export']);

    $this->actingAs($staff)->get('/admin/exports')
        ->assertOk()
        ->assertSee(__('admin.exports.empty_title'));
});

test('the owner sees every entity on the exports screen', function () {
    $owner = $this->createStaff();

    $response = $this->actingAs($owner)->get('/admin/exports')->assertOk();
    foreach (array_keys(DATA_EXPORT_ENTITY_PERMISSIONS) as $entity) {
        $response->assertSee(__('admin.exports.entities.'.$entity));
    }
});

test('download is forbidden without data.export', function (string $entity) {
    $staff = $this->createStaff([], [DATA_EXPORT_ENTITY_PERMISSIONS[$entity]]);

    $this->actingAs($staff)
        ->get(route('admin.exports.download', ['entity' => $entity, 'format' => 'csv']))
        ->assertForbidden();
})->with('data export entities');

test('download is forbidden with data.export but without the entity view permission', function (string $entity) {
    $staff = $this->createStaff([], ['data.export']);

    $this->actingAs($staff)
        ->get(route('admin.exports.download', ['entity' => $entity, 'format' => 'csv']))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'data.exported')->count())->toBe(0);
})->with('data export entities');

test('download is allowed with data.export and the entity view permission', function (string $entity) {
    $staff = $this->createStaff([], ['data.export', DATA_EXPORT_ENTITY_PERMISSIONS[$entity]]);

    $this->actingAs($staff)
        ->get(route('admin.exports.download', ['entity' => $entity, 'format' => 'csv']))
        ->assertOk();
})->with('data export entities');

test('download rejects unknown entities and formats', function () {
    $owner = $this->createStaff();

    $this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'users', 'format' => 'csv']))
        ->assertSessionHasErrors('entity');
    $this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'orders', 'format' => 'xlsx']))
        ->assertSessionHasErrors('format');
    $this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'orders', 'format' => 'csv', 'created_from' => '2026-10-08', 'created_to' => '2026-10-01']))
        ->assertSessionHasErrors('created_to');
});

test('each entity exports in each format with the documented columns', function (string $entity, string $format) {
    Carbon::setTestNow('2026-10-08 12:00:00');
    $owner = $this->createStaff();
    $markers = seedDataExportFixtures();

    $response = $this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => $entity, 'format' => $format]))
        ->assertOk();

    $contentTypes = [
        'csv' => 'text/csv; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8',
    ];
    expect($response->headers->get('Content-Type'))->toBe($contentTypes[$format])
        ->and($response->headers->get('Content-Disposition'))->toContain("agovena-{$entity}-2026-10-08.{$format}");

    $content = downloadDataExport($response);
    expect($content)->toContain($markers[$entity]);

    $records = match ($format) {
        'csv' => parseDataExportCsv($content),
        'json' => json_decode($content, true, 512, JSON_THROW_ON_ERROR),
        'xml' => (function () use ($content, $entity): array {
            $xml = simplexml_load_string($content);
            expect($xml)->not->toBeFalse()
                ->and($xml->getName())->toBe($entity);
            $rows = [];
            foreach ($xml->children() as $record) {
                $rows[] = array_map('strval', (array) $record);
            }

            return $rows;
        })(),
    };

    expect($records)->not->toBeEmpty();
    $first = $records[0];
    expect(array_keys($first))->toBe(app(ExportDatasetRegistry::class)->find($entity)->columns());

    if ($entity === 'orders') {
        expect((string) $first['total'])->toBe('24.19')
            ->and($first['currency'])->toBe('EUR')
            ->and($first['customer_email'])->toBe('ada@export.test')
            ->and($first['created_at'])->toBe('2026-10-08T12:00:00Z');
    }

    if ($entity === 'invoices') {
        expect(collect($records)->pluck('document_type')->all())->toBe(['invoice', 'credit_note'])
            ->and($records[1]['credited_invoice_number'])->toBe('INV-EXPORT-0001')
            ->and((string) $records[1]['total'])->toBe('6.05');
    }
})->with('data export entities')->with('data export formats');

test('exports never contain secrets', function (string $format) {
    $owner = $this->createStaff();
    seedDataExportFixtures();

    $secrets = [
        'hash-secret-PASSWORD-MARKER', 'TWO-FACTOR-SECRET-MARKER', 'RECOVERY-CODES-MARKER',
        'REMEMBER-TOKEN-MARKER', 'STOREFRONT-TOKEN-MARKER', 'IDEMPOTENCY-KEY-MARKER',
        'IDEMPOTENCY-OWNER-MARKER', 'PROVIDER-RAW-PAYLOAD-MARKER',
    ];

    foreach (array_keys(DATA_EXPORT_ENTITY_PERMISSIONS) as $entity) {
        $content = downloadDataExport($this->actingAs($owner)
            ->get(route('admin.exports.download', ['entity' => $entity, 'format' => $format]))
            ->assertOk());

        foreach ($secrets as $secret) {
            expect($content)->not->toContain($secret);
        }
        expect(strtolower($content))->not->toContain('password')
            ->and(strtolower($content))->not->toContain('two_factor')
            ->and(strtolower($content))->not->toContain('storefront_token')
            ->and(strtolower($content))->not->toContain('reconciliation_meta');
    }
})->with('data export formats');

test('csv cells that could run as spreadsheet formulas are neutralised', function () {
    $owner = $this->createStaff();
    $dangerous = ['=HYPERLINK("http://evil.test")', '+SUM(A1)', '-2+3', '@cmd', "\tTabbed", "\rReturn"];
    foreach ($dangerous as $index => $name) {
        Customer::factory()->create(['name' => $name, 'email' => "formula{$index}@export.test"]);
    }

    $rows = parseDataExportCsv(downloadDataExport($this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'customers', 'format' => 'csv']))
        ->assertOk()));

    $names = collect($rows)->pluck('name')->all();
    foreach ($dangerous as $name) {
        expect($names)->toContain("'".$name);
    }
});

test('csv quotes delimiters, quotes and newlines per RFC 4180 and keeps negative amounts numeric', function () {
    $owner = $this->createStaff();
    Customer::factory()->create(['name' => "Smith, \"Jr\"\nSecond line", 'email' => 'quote@export.test']);

    $content = downloadDataExport($this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'customers', 'format' => 'csv']))
        ->assertOk());

    expect($content)->toContain("\"Smith, \"\"Jr\"\"\nSecond line\"")
        ->and($content)->toContain("\r\n");

    $writer = app(ExportStreamWriter::class);
    $values = app(ExportValues::class);
    expect($writer->csvCell('-12.50'))->toBe('-12.50')
        ->and($writer->csvCell(-5))->toBe('-5')
        ->and($writer->csvCell(true))->toBe('true')
        ->and($values->money(-1250, 'EUR'))->toBe('-12.50')
        ->and($values->money(5, 'EUR'))->toBe('0.05');
});

test('xml output escapes markup and strips invalid control characters', function () {
    $owner = $this->createStaff();
    Customer::factory()->create(['name' => "<b>Tom & \"Jerry\"</b>\x01", 'email' => 'xml@export.test']);

    $content = downloadDataExport($this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'customers', 'format' => 'xml']))
        ->assertOk());

    $xml = simplexml_load_string($content);
    expect($xml)->not->toBeFalse();
    $names = [];
    foreach ($xml->customer as $record) {
        $names[] = (string) $record->name;
    }
    expect($names)->toContain('<b>Tom & "Jerry"</b>')
        ->and($content)->toContain('&lt;b&gt;Tom &amp;');
});

test('the created-at date range limits exported rows', function (string $format) {
    $owner = $this->createStaff();
    foreach (['2026-09-01 10:00:00' => 'AGO-SEPT', '2026-10-02 23:59:59' => 'AGO-OCT-A', '2026-10-05 00:00:00' => 'AGO-OCT-B', '2026-11-01 08:00:00' => 'AGO-NOV'] as $at => $number) {
        $order = Order::factory()->create(['number' => $number]);
        $order->forceFill(['created_at' => $at])->saveQuietly();
    }

    $content = downloadDataExport($this->actingAs($owner)
        ->get(route('admin.exports.download', [
            'entity' => 'orders',
            'format' => $format,
            'created_from' => '2026-10-02',
            'created_to' => '2026-10-31',
        ]))
        ->assertOk());

    expect($content)->toContain('AGO-OCT-A')
        ->and($content)->toContain('AGO-OCT-B')
        ->and($content)->not->toContain('AGO-SEPT')
        ->and($content)->not->toContain('AGO-NOV');
})->with('data export formats');

test('an empty export is still a valid document', function (string $format) {
    $owner = $this->createStaff();

    $content = downloadDataExport($this->actingAs($owner)
        ->get(route('admin.exports.download', ['entity' => 'orders', 'format' => $format]))
        ->assertOk());

    match ($format) {
        'csv' => expect(parseDataExportCsv($content))->toBe([]),
        'json' => expect(json_decode($content, true, 512, JSON_THROW_ON_ERROR))->toBe([]),
        'xml' => expect(simplexml_load_string($content)?->getName())->toBe('orders'),
    };
})->with('data export formats');

test('every export writes an audit entry without exported data', function () {
    $owner = $this->createStaff();
    seedDataExportFixtures();

    downloadDataExport($this->actingAs($owner)
        ->get(route('admin.exports.download', [
            'entity' => 'customers',
            'format' => 'json',
            'created_from' => '2020-01-01',
        ]))
        ->assertOk());

    $log = AuditLog::query()->where('action', 'data.exported')->sole();
    expect($log->actor_type)->toBe('staff')
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->properties)->toMatchArray([
            'entity' => 'customers',
            'format' => 'json',
            'filters' => ['created_from' => '2020-01-01', 'created_to' => null],
            'row_count' => Customer::query()->count(),
        ])
        ->and(json_encode($log->toArray()))->not->toContain('ada@export.test')
        ->and(json_encode($log->toArray()))->not->toContain('Ada Export');
});

test('the export form redirects to the download for a permitted entity', function () {
    $staff = $this->createStaff([], ['data.export', 'orders.view']);

    Livewire::actingAs($staff)
        ->test(ExportsIndex::class)
        ->set('entity', 'orders')
        ->set('format', 'xml')
        ->set('createdFrom', '2026-10-01')
        ->set('createdTo', '2026-10-08')
        ->call('export')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.exports.download', [
            'entity' => 'orders',
            'format' => 'xml',
            'created_from' => '2026-10-01',
            'created_to' => '2026-10-08',
        ]));
});

test('the export form rejects entities the staff member cannot view', function () {
    $staff = $this->createStaff([], ['data.export', 'orders.view']);

    Livewire::actingAs($staff)
        ->test(ExportsIndex::class)
        ->set('entity', 'customers')
        ->call('export')
        ->assertHasErrors('entity')
        ->assertNoRedirect();
});

test('the export form reauthorizes data.export on every request', function () {
    $staff = $this->createStaff([], ['data.export', 'orders.view']);
    $component = Livewire::actingAs($staff)->test(ExportsIndex::class);

    $staff->roles()->firstOrFail()->revokePermissionTo('data.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('export')->assertForbidden();
});
