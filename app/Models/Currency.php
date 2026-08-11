<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $prefix
 * @property string $suffix
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'prefix', 'suffix', 'is_active'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function formatMinorUnits(int $amount): string
    {
        $number = number_format($amount / 100, 2, '.', ',');

        return trim($this->prefix.$number.$this->suffix);
    }

    public function previewSample(): string
    {
        return $this->formatMinorUnits(12345);
    }
}
