<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $historicalMigration = require database_path('migrations/2026_08_28_115500_split_domain_provider_extensions.php');
        $definitionsMethod = new ReflectionMethod($historicalMigration, 'definitions');
        $definitionsMethod->setAccessible(true);

        foreach ($definitionsMethod->invoke($historicalMigration) as $targetId => $definition) {
            $groups = [];
            foreach ($definition['setting_map'] as $source => $targetKey) {
                if (($definition['setting_secrets'][$targetKey] ?? false) !== true) {
                    continue;
                }

                [$sourceExtensionId, $sourceKey] = explode(':', $source, 2);
                $row = DB::table('extension_settings')
                    ->where('extension_id', $sourceExtensionId)
                    ->where('key', $sourceKey)
                    ->first();
                if ($row !== null) {
                    $groups[$targetKey][] = $row;
                }
            }

            $targetRows = DB::table('extension_settings')
                ->where('extension_id', $targetId)
                ->whereIn('key', array_keys($groups))
                ->get()
                ->keyBy('key');
            foreach ($groups as $targetKey => $rows) {
                if ($targetRows->has($targetKey)) {
                    $rows[] = $targetRows->get($targetKey);
                }

                if (count($rows) < 2) {
                    continue;
                }

                $canonical = $this->canonicalValue($rows);
                if ($canonical === null) {
                    continue;
                }

                foreach ($rows as $row) {
                    if ($row->value !== $canonical) {
                        DB::table('extension_settings')
                            ->where('id', $row->id)
                            ->update([
                                'value' => $canonical,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
        }
    }

    public function down(): void {}

    /** @param list<object> $rows */
    private function canonicalValue(array $rows): ?string
    {
        $normalized = array_map(function (object $row): array {
            try {
                return [
                    'raw' => (string) $row->value,
                    'plain' => Crypt::decryptString((string) $row->value),
                    'encrypted' => true,
                ];
            } catch (Throwable) {
                return [
                    'raw' => (string) $row->value,
                    'plain' => (string) $row->value,
                    'encrypted' => false,
                ];
            }
        }, $rows);

        $plainValues = array_values(array_unique(array_column($normalized, 'plain')));
        if (count($plainValues) !== 1) {
            return null;
        }

        foreach ($normalized as $value) {
            if ($value['encrypted'] === true) {
                return $value['raw'];
            }
        }

        return $normalized[0]['raw'] ?? null;
    }
};
