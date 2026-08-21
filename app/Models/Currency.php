<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $prefix
 * @property string $suffix
 * @property int $precision
 * @property string $exchange_rate
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'prefix', 'suffix', 'precision', 'exchange_rate', 'is_active'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precision' => 'integer',
            'is_active' => 'boolean',
            'exchange_rate' => 'decimal:8',
        ];
    }

    /**
     * Format integer minor units using this currency's precision (no floats).
     */
    public function formatMinorUnits(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        $precision = $this->normalizedPrecision();
        $number = $this->formatScaledInteger($amount, $precision);

        return trim($this->prefix.$number.$this->suffix);
    }

    public function previewSample(): string
    {
        $precision = $this->normalizedPrecision();
        $sample = $precision === 0 ? 1234 : (10 ** $precision) * 123 + (10 ** max(0, $precision - 2)) * 45;

        return $this->formatMinorUnits($sample);
    }

    public function scale(): int
    {
        return 10 ** $this->normalizedPrecision();
    }

    public function normalizedPrecision(): int
    {
        if (! array_key_exists('precision', $this->attributes) || $this->attributes['precision'] === null || $this->attributes['precision'] === '') {
            return 2;
        }

        $precision = (int) $this->attributes['precision'];

        if ($precision < 0 || $precision > 6) {
            return 2;
        }

        return $precision;
    }

    private function formatScaledInteger(int $amount, int $precision): string
    {
        if ($precision === 0) {
            return $this->groupThousands($amount);
        }

        $scale = 10 ** $precision;
        $whole = intdiv($amount, $scale);
        $fraction = $amount % $scale;

        return $this->groupThousands($whole).'.'.str_pad((string) $fraction, $precision, '0', STR_PAD_LEFT);
    }

    private function groupThousands(int $value): string
    {
        $digits = (string) $value;
        $length = strlen($digits);
        if ($length <= 3) {
            return $digits;
        }

        $grouped = '';
        for ($i = 0; $i < $length; $i++) {
            if ($i > 0 && ($length - $i) % 3 === 0) {
                $grouped .= ',';
            }
            $grouped .= $digits[$i];
        }

        return $grouped;
    }
}
