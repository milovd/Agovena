<?php

declare(strict_types=1);

namespace App\Agovena\Security;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class OrderItemRuntimeSecretStore
{
    public function put(int $orderItemId, string $key, mixed $value): void
    {
        if ($value === null) {
            $this->forget($orderItemId, $key);

            return;
        }

        $now = now();
        DB::table('order_item_runtime_secrets')->updateOrInsert(
            ['order_item_id' => $orderItemId, 'key' => $key],
            [
                'value_encrypted' => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function get(int $orderItemId, string $key): mixed
    {
        $encrypted = DB::table('order_item_runtime_secrets')
            ->where('order_item_id', $orderItemId)
            ->where('key', $key)
            ->value('value_encrypted');
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Runtime secret cannot be decrypted.', previous: $exception);
        }
    }

    public function forget(int $orderItemId, string $key): void
    {
        DB::table('order_item_runtime_secrets')
            ->where('order_item_id', $orderItemId)
            ->where('key', $key)
            ->delete();
    }
}
