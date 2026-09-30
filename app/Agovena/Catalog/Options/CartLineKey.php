<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Options;

use LogicException;

final class CartLineKey
{
    /**
     * @param  array<string, mixed>  $selections
     */
    public static function make(int $productId, array $selections = []): string
    {
        $normalized = self::normalize($selections);
        if ($normalized === []) {
            return (string) $productId;
        }

        $applicationKey = config('app.key');
        if (! is_string($applicationKey) || $applicationKey === '') {
            throw new LogicException('The application key is required to derive configurable cart line keys.');
        }

        $payload = $productId.':'.json_encode($normalized, JSON_THROW_ON_ERROR);

        return $productId.':'.hash_hmac('sha256', $payload, $applicationKey);
    }

    /**
     * @param  array<string, mixed>  $selections
     * @return array<string, mixed>
     */
    public static function normalize(array $selections): array
    {
        $clean = [];
        foreach ($selections as $key => $value) {
            $name = strtolower(trim((string) $key));
            if ($name === '' || $value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                if ($value) {
                    $clean[$name] = true;
                }

                continue;
            }
            if (is_array($value)) {
                $items = [];
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $items[] = $item;
                    }
                }
                sort($items);
                if ($items !== []) {
                    $clean[$name] = $items;
                }

                continue;
            }
            $clean[$name] = is_numeric($value) && ! is_string($value) ? $value : trim((string) $value);
        }
        ksort($clean);

        return $clean;
    }
}
