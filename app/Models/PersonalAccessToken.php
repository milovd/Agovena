<?php

declare(strict_types=1);

namespace App\Models;

class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    /** @var array<string, string> */
    protected $casts = [
        'abilities' => 'json',
        'ip_allowlist' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'ip_allowlist',
        'expires_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'token',
    ];
}
