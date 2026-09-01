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
        if (! Schema::hasTable('extension_settings')
            || ! Schema::hasColumn('extension_settings', 'is_secret')
            || ! Schema::hasColumn('extension_settings', 'value')
        ) {
            return;
        }

        $query = DB::table('extension_settings')
            ->where('is_secret', true)
            ->whereNotNull('value');
        if (Schema::hasColumn('extension_settings', 'is_corrupt')) {
            $query->where('is_corrupt', false);
        }

        $query->select(['id', 'value'])->chunkById(500, function ($settings): void {
            foreach ($settings as $setting) {
                $value = $setting->value;
                if (! is_string($value) || $value === '' || $value === '[REDACTED]') {
                    continue;
                }

                try {
                    Crypt::decryptString($value);
                } catch (Throwable) {
                    DB::table('extension_settings')->where('id', $setting->id)->update([
                        'value' => Crypt::encryptString($value),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void {}
};
