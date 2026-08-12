<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Settings\SettingsRepository;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

final class InvoiceNumberGenerator
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function next(): string
    {
        return DB::transaction(function (): string {
            $prefix = strtoupper(preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                (string) $this->settings->get('store', 'invoice_number_prefix', 'INV'),
            ) ?: 'INV');

            $year = now()->format('Y');
            $sequenceKey = "invoice_sequence_{$year}";
            $current = (int) $this->settings->get('store', $sequenceKey, 0);
            $next = $current + 1;
            $this->settings->set('store', $sequenceKey, $next);

            $number = sprintf('%s-%s-%05d', $prefix, $year, $next);

            while (Invoice::query()->where('number', $number)->exists()) {
                $next++;
                $this->settings->set('store', $sequenceKey, $next);
                $number = sprintf('%s-%s-%05d', $prefix, $year, $next);
            }

            return $number;
        });
    }
}
