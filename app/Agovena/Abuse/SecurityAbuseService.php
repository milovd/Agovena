<?php

declare(strict_types=1);

namespace App\Agovena\Abuse;

use App\Models\SecurityIpRule;
use App\Models\SecurityUserSuspension;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SecurityAbuseService
{
    public function blockIp(string $ip, string $reason, ?CarbonInterface $expiresAt = null): SecurityIpRule
    {
        return SecurityIpRule::query()->updateOrCreate(
            ['ip_hash' => $this->hashIp($ip), 'rule_type' => 'block'],
            ['reason' => $reason, 'expires_at' => $expiresAt, 'created_by' => auth()->id()],
        );
    }

    public function allowIp(string $ip, string $reason, ?CarbonInterface $expiresAt = null): SecurityIpRule
    {
        return SecurityIpRule::query()->updateOrCreate(
            ['ip_hash' => $this->hashIp($ip), 'rule_type' => 'allow'],
            ['reason' => $reason, 'expires_at' => $expiresAt, 'created_by' => auth()->id()],
        );
    }

    public function isIpAllowed(string $ip): bool
    {
        return SecurityIpRule::query()
            ->where('ip_hash', $this->hashIp($ip))
            ->where('rule_type', 'allow')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function isIpBlocked(string $ip): bool
    {
        if ($this->isIpAllowed($ip)) {
            return false;
        }

        return SecurityIpRule::query()
            ->where('ip_hash', $this->hashIp($ip))
            ->where('rule_type', 'block')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function suspendUser(User $user, string $reason, ?CarbonInterface $expiresAt = null): SecurityUserSuspension
    {
        return SecurityUserSuspension::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['reason' => $reason, 'expires_at' => $expiresAt, 'created_by' => auth()->id()],
        );
    }

    public function unsuspendUser(User $user): void
    {
        SecurityUserSuspension::query()->where('user_id', $user->getKey())->delete();
    }

    public function isUserSuspended(User $user): bool
    {
        return SecurityUserSuspension::query()
            ->where('user_id', $user->getKey())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function recoverIp(string $ip): void
    {
        DB::transaction(function () use ($ip): void {
            SecurityIpRule::query()
                ->where('ip_hash', $this->hashIp($ip))
                ->where('rule_type', 'block')
                ->delete();
            $this->allowIp($ip, 'operator recovery');
        });
    }

    public function hashIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Invalid IP address.');
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
