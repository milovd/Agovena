<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ip_hash', 'rule_type', 'reason', 'expires_at', 'created_by'])]
final class SecurityIpRule extends Model
{
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
