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
        if (! Schema::hasTable('order_items') || ! Schema::hasTable('order_item_runtime_secrets')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('order_items', 'provisioning_server_settings_snapshot')
                ? 'provisioning_server_settings_snapshot'
                : null,
            Schema::hasColumn('order_items', 'provisioning_provider_settings_snapshot')
                ? 'provisioning_provider_settings_snapshot'
                : null,
        ]));
        if ($columns === []) {
            return;
        }

        DB::table('order_items')->select(array_merge(['id'], $columns))->chunkById(500, function ($items) use ($columns): void {
            foreach ($items as $item) {
                $updates = [];
                foreach ($columns as $column) {
                    $settings = $this->decrypt($item->{$column} ?? null);
                    if ($settings !== null) {
                        $key = $column === 'provisioning_server_settings_snapshot'
                            ? 'provisioning_server_settings'
                            : 'provisioning_provider_settings';
                        DB::table('order_item_runtime_secrets')->updateOrInsert(
                            ['order_item_id' => $item->id, 'key' => $key],
                            [
                                'value_encrypted' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        );
                    }
                    $updates[$column] = null;
                }
                DB::table('order_items')->where('id', $item->id)->update($updates);
            }
        });
    }

    public function down(): void {}

    /** @return array<string, mixed>|null */
    private function decrypt(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Legacy order settings could not be migrated.', previous: $exception);
        }

        return is_array($decoded) ? $decoded : null;
    }
};
