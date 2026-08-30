<?php

declare(strict_types=1);

use App\Agovena\Security\SensitiveDataRedactor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_capabilities')) {
            return;
        }

        if (! Schema::hasColumn('product_capabilities', 'config_encrypted')) {
            Schema::table('product_capabilities', function (Blueprint $table): void {
                $table->text('config_encrypted')->nullable()->after('config');
            });
        }

        foreach (DB::table('product_capabilities')
            ->whereNotNull('config')
            ->where(function ($query): void {
                $query->whereNull('config_encrypted')->orWhere('config_encrypted', '');
            })
            ->orderBy('id')
            ->get(['id', 'config']) as $row) {
            $config = json_decode((string) $row->config, true);
            if (! is_array($config)) {
                $config = ['_migration_status' => 'invalid_legacy_config'];
            }

            DB::table('product_capabilities')->where('id', $row->id)->update([
                'config' => json_encode(self::sanitizePublicConfig($config), JSON_THROW_ON_ERROR),
                'config_encrypted' => Crypt::encryptString(json_encode($config, JSON_THROW_ON_ERROR)),
            ]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Encrypted product capability configuration migration is irreversible.');
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private static function sanitizePublicConfig(array $value): array
    {
        $sanitized = SensitiveDataRedactor::redact($value);

        return is_array($sanitized) ? $sanitized : [];
    }
};
