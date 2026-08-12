<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Lang;

final class ResetCustomerPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    protected function buildMailMessage($url)
    {
        return parent::buildMailMessage($url)
            ->subject(Lang::get('customer.auth.reset_password_subject'));
    }
}
