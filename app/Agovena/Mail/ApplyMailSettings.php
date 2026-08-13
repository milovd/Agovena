<?php

declare(strict_types=1);

namespace App\Agovena\Mail;

use App\Agovena\Installation\InstallationState;
use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\Mail;

final class ApplyMailSettings
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly InstallationState $installation,
    ) {}

    public function __invoke(): void
    {
        if ($this->installation->notInstalled()) {
            return;
        }

        $address = trim((string) $this->settings->get('mail', 'from_address', ''));
        $name = trim((string) $this->settings->get('mail', 'from_name', ''));
        $replyTo = trim((string) $this->settings->get('mail', 'reply_to', ''));

        if ($address !== '') {
            $fromName = $name !== '' ? $name : (string) config('mail.from.name');
            config([
                'mail.from.address' => $address,
                'mail.from.name' => $fromName,
            ]);
            Mail::alwaysFrom($address, $fromName !== '' ? $fromName : null);
        } elseif ($name !== '') {
            config(['mail.from.name' => $name]);
        }

        if ($replyTo !== '') {
            Mail::alwaysReplyTo($replyTo);
        }
    }
}
