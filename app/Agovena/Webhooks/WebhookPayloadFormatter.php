<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

final class WebhookPayloadFormatter
{
    public static function format(?string $destination, mixed $payload): array
    {
        $payload = is_array($payload) ? $payload : [];
        if ($destination !== 'discord') {
            return $payload;
        }

        $type = (string) ($payload['type'] ?? 'event');
        $data = $payload['data'] ?? [];
        $description = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'embeds' => [[
                'title' => 'Agovena event: '.$type,
                'description' => mb_substr($description, 0, 4000),
            ]],
        ];
    }
}
