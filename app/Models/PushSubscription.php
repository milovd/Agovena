<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Minishlink\WebPush\Subscription;

final class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh_key',
        'auth_key',
        'user_agent',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'p256dh_key' => 'encrypted',
            'auth_key' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): Subscription
    {
        return Subscription::create([
            'endpoint' => $this->endpoint,
            'publicKey' => $this->p256dh_key,
            'authToken' => $this->auth_key,
        ]);
    }
}
