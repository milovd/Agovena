<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')
            || ! Schema::hasTable('order_item_runtime_secrets')
            || ! Schema::hasColumn('order_items', 'options_snapshot')
        ) {
            return;
        }

        DB::table('order_items')
            ->whereNotNull('options_snapshot')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    $snapshot = json_decode((string) $item->options_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($snapshot)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($snapshot as $index => $row) {
                        if (! is_array($row) || ! array_key_exists('value_encrypted', $row)) {
                            continue;
                        }

                        $key = $row['key'] ?? null;
                        if (! is_string($key) || trim($key) === '') {
                            throw new RuntimeException('Legacy encrypted product option has no key.');
                        }

                        $value = $this->decrypt($row['value_encrypted']);
                        DB::table('order_item_runtime_secrets')->updateOrInsert(
                            ['order_item_id' => $item->id, 'key' => $key],
                            [
                                'value_encrypted' => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        );
                        $snapshot[$index]['value'] = '[REDACTED]';
                        unset($snapshot[$index]['value_encrypted']);
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('order_items')
                            ->where('id', $item->id)
                            ->update(['options_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                    }
                }
            });

        if (! Schema::hasTable('invoice_items') || ! Schema::hasColumn('invoice_items', 'options_snapshot')) {
            return;
        }

        DB::table('invoice_items')
            ->whereNotNull('options_snapshot')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    $snapshot = json_decode((string) $item->options_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($snapshot)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($snapshot as $index => $row) {
                        if (! is_array($row) || ! array_key_exists('value_encrypted', $row)) {
                            continue;
                        }

                        $snapshot[$index]['value'] = '[REDACTED]';
                        unset($snapshot[$index]['value_encrypted']);
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('invoice_items')
                            ->where('id', $item->id)
                            ->update(['options_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                    }
                }
            });
    }

    public function down(): void {}

    private function decrypt(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('Legacy encrypted product option is invalid.');
        }

        try {
            return json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Legacy encrypted product option could not be migrated.', previous: $exception);
        }
    }
};
