<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class VapidKeyStore
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** @param array{publicKey: string, privateKey: string} $keys */
    public function put(string $subject, array $keys): void
    {
        if ($subject === '' || $keys['publicKey'] === '' || $keys['privateKey'] === '') {
            throw new RuntimeException('Incomplete VAPID configuration.');
        }

        $this->settings->setMany('notifications', [
            'vapid_subject' => $subject,
            'vapid_public_key' => $keys['publicKey'],
            'vapid_private_key' => Crypt::encryptString($keys['privateKey']),
        ]);
    }

    /** @return array{subject: string, publicKey: string, privateKey: string}|null */
    public function get(): ?array
    {
        $subject = $this->settings->get('notifications', 'vapid_subject');
        $publicKey = $this->settings->get('notifications', 'vapid_public_key');
        $encryptedPrivateKey = $this->settings->get('notifications', 'vapid_private_key');

        if (! is_string($subject) || ! is_string($publicKey) || ! is_string($encryptedPrivateKey)) {
            return null;
        }

        try {
            $privateKey = Crypt::decryptString($encryptedPrivateKey);
        } catch (RuntimeException) {
            return null;
        }

        return [
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ];
    }
}
