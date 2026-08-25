<?php

declare(strict_types=1);

namespace App\Agovena\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManageUserSessions
{
    public function usesDatabaseDriver(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return list<array{id: string, ip_address: string|null, user_agent: string|null, last_activity: Carbon, is_current: bool, device_label: string}>
     */
    public function listFor(User $user, ?string $currentSessionId = null): array
    {
        if (! $this->usesDatabaseDriver()) {
            return [];
        }

        $currentSessionId ??= session()->getId();

        $sessions = [];
        foreach (
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity']) as $row
        ) {
            $agent = is_string($row->user_agent) ? $row->user_agent : null;
            $sessions[] = [
                'id' => (string) $row->id,
                'ip_address' => is_string($row->ip_address) ? $row->ip_address : null,
                'user_agent' => $agent,
                'last_activity' => Carbon::createFromTimestamp((int) $row->last_activity),
                'is_current' => (string) $row->id === (string) $currentSessionId,
                'device_label' => $this->deviceLabel($agent),
            ];
        }

        return $sessions;
    }

    public function revoke(User $user, string $sessionId, ?string $currentSessionId = null): bool
    {
        if (! $this->usesDatabaseDriver()) {
            return false;
        }

        $currentSessionId ??= session()->getId();
        if ($sessionId === '' || $sessionId === $currentSessionId) {
            return false;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeOthers(User $user, ?string $currentSessionId = null): int
    {
        if (! $this->usesDatabaseDriver()) {
            return 0;
        }

        $currentSessionId ??= session()->getId();

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function deviceLabel(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return __('customer.security.session_unknown_device');
        }

        $browser = match (true) {
            Str::contains($userAgent, 'Edg/') => 'Edge',
            Str::contains($userAgent, 'Chrome/') => 'Chrome',
            Str::contains($userAgent, 'Firefox/') => 'Firefox',
            Str::contains($userAgent, 'Safari/') => 'Safari',
            default => __('customer.security.session_unknown_device'),
        };

        $platform = match (true) {
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'iPhone') || Str::contains($userAgent, 'iPad') => 'iOS',
            Str::contains($userAgent, 'Mac OS') => 'macOS',
            Str::contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $platform !== null ? "{$browser} / {$platform}" : $browser;
    }
}
