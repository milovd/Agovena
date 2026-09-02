<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Audit\AuditLogger;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateInvoice
{
    /** @var list<string> */
    private const EDITABLE_ATTRIBUTES = [
        'customer_name',
        'customer_email',
        'billing_name',
        'billing_company',
        'billing_line1',
        'billing_line2',
        'billing_city',
        'billing_region',
        'billing_postal_code',
        'billing_country',
        'billing_phone',
        'merchant_name',
        'merchant_address',
        'due_at',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Invoice $invoice, array $attributes): Invoice
    {
        return DB::transaction(function () use ($invoice, $attributes): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $data = array_intersect_key($attributes, array_flip(self::EDITABLE_ATTRIBUTES));
            $before = $locked->only(self::EDITABLE_ATTRIBUTES);

            $locked->fill($data);
            if ($locked->isDirty()) {
                $locked->save();
                $this->audit->logChange(
                    'invoice.updated',
                    $locked,
                    $before,
                    $locked->only(self::EDITABLE_ATTRIBUTES),
                    ['invoice_number' => $locked->number],
                );
            }

            return $locked->fresh(['items', 'order.payment', 'creditNotes', 'refunds'])
                ?? throw new RuntimeException('Invoice disappeared after update.');
        });
    }
}
