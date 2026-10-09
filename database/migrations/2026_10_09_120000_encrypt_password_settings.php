<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Core password settings used to be stored as plain text. Encrypt existing values with
 * the application key, in the same "encrypted:" format SettingsRepository::setSecret writes.
 */
return new class extends Migration
{
    private const PREFIX = 'encrypted:';

    /** @var list<array{0: string, 1: string}> */
    private const SECRETS = [
        ['store', 'google_places_api_key'],
    ];

    public function up(): void
    {
        foreach (self::SECRETS as [$group, $key]) {
            $value = DB::table('settings')->where('group', $group)->where('key', $key)->value('value');
            if (! is_string($value) || $value === '' || str_starts_with($value, self::PREFIX)) {
                continue;
            }

            $this->write($group, $key, self::PREFIX.Crypt::encryptString($value));
        }
    }

    public function down(): void
    {
        foreach (self::SECRETS as [$group, $key]) {
            $value = DB::table('settings')->where('group', $group)->where('key', $key)->value('value');
            if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
                continue;
            }

            try {
                $plain = Crypt::decryptString(substr($value, strlen(self::PREFIX)));
            } catch (DecryptException) {
                continue;
            }

            $this->write($group, $key, $plain);
        }
    }

    private function write(string $group, string $key, string $value): void
    {
        DB::table('settings')->where('group', $group)->where('key', $key)->update(['value' => $value]);
        Cache::forget('agovena.settings.'.$group.'.'.$key);
    }
};
