<?php

declare(strict_types=1);

namespace App\Agovena\Auth;

use App\Agovena\Audit\AuditLogger;
use App\Models\User;

final class SetUserEmailVerification
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, bool $verified): User
    {
        if ($user->hasVerifiedEmail() === $verified) {
            return $user;
        }

        $user->forceFill([
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        $this->audit->log(
            $verified ? 'user.email_verified' : 'user.email_unverified',
            $user,
            ['user_id' => $user->getKey()],
        );

        return $user;
    }
}
