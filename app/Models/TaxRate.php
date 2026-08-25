<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'rate_bps', 'country', 'region', 'is_active', 'is_disabled', 'applies_to_shipping'])]
class TaxRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'is_active' => 'boolean',
            'is_disabled' => 'boolean',
            'applies_to_shipping' => 'boolean',
        ];
    }
}
