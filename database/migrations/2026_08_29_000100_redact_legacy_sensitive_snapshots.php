<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->redactJsonColumn('order_items', 'options_snapshot');
        $this->redactJsonColumn('invoice_items', 'options_snapshot');
        $this->redactJsonColumn('import_rows', 'payload');
    }

    public function down(): void
    {
        // Redaction is intentionally irreversible.
    }

    private function redactJsonColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->{$column}, true);
                    $value = is_array($decoded)
                        ? $this->redact($decoded)
                        : ['_redaction_status' => 'invalid_legacy_json'];

                    DB::table($table)->where('id', $row->id)->update([
                        $column => json_encode($value, JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $nestedKey => $nestedValue) {
            if (is_array($nestedValue)
                && is_string($nestedValue['key'] ?? null)
                && array_key_exists('value', $nestedValue)
                && $this->isSensitiveKey($nestedValue['key'])
            ) {
                $nestedValue['value'] = '[REDACTED]';
                if (array_key_exists('display', $nestedValue)) {
                    $nestedValue['display'] = '[REDACTED]';
                }
            }

            $redacted[$nestedKey] = $this->redact(
                $nestedValue,
                is_string($nestedKey) ? $nestedKey : null,
            );
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(trim($key));

        return $normalizedKey === 'environment'
            || $normalizedKey === 'server_settings'
            || preg_match('/(?:api[_-]?key|access[_-]?key|token|secret|password|passwd|credential|authorization|private[_-]?key|connection|string|dsn)/', $normalizedKey) === 1;
    }
};
