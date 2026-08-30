<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProductPlanChangeRequest;
use Closure;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class PlanChangeApplied
{
    use Dispatchable;
    use SerializesModels;

    /** @var list<Closure(): void> */
    private array $compensations = [];

    public function __construct(
        public ProductPlanChangeRequest $request,
    ) {}

    public function registerCompensation(Closure $compensation): void
    {
        $this->compensations[] = $compensation;
    }

    public function compensate(): void
    {
        $failures = [];

        foreach (array_reverse($this->compensations) as $compensation) {
            try {
                $compensation();
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = $exception;
            }
        }

        if ($failures !== []) {
            throw $failures[0];
        }
    }
}
