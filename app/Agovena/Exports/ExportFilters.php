<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Created-at date range for an export. Both bounds are inclusive calendar days in UTC.
 */
final readonly class ExportFilters
{
    public function __construct(
        public ?CarbonImmutable $createdFrom = null,
        public ?CarbonImmutable $createdTo = null,
    ) {}

    /**
     * @param  string|null  $createdFrom  Y-m-d
     * @param  string|null  $createdTo  Y-m-d
     */
    public static function fromDates(?string $createdFrom, ?string $createdTo): self
    {
        return new self(
            self::day($createdFrom),
            self::day($createdTo),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        $column = $query->getModel()->qualifyColumn('created_at');

        if ($this->createdFrom !== null) {
            $query->where($column, '>=', $this->createdFrom->startOfDay());
        }

        if ($this->createdTo !== null) {
            $query->where($column, '<', $this->createdTo->startOfDay()->addDay());
        }

        return $query;
    }

    /** @return array{created_from: string|null, created_to: string|null} */
    public function toArray(): array
    {
        return [
            'created_from' => $this->createdFrom?->format('Y-m-d'),
            'created_to' => $this->createdTo?->format('Y-m-d'),
        ];
    }

    private static function day(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', trim($value), 'UTC');

        return $day instanceof CarbonImmutable ? $day : null;
    }
}
