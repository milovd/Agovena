<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

final class VerifyCustomerEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'customer.verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
