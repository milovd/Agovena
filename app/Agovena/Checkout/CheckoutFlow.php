<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

final class CheckoutFlow
{
    /**
     * @return list<CheckoutStep>
     */
    public function stepsFor(CartRequirements $requirements): array
    {
        $steps = [CheckoutStep::Details];
        $needsDelivery = $requirements->requiresShipping();
        $needsConfiguration = $requirements->has(CartRequirement::ProductConfiguration);

        if ($needsDelivery && $needsConfiguration) {
            $steps[] = CheckoutStep::Fulfillment;
        } elseif ($needsDelivery) {
            $steps[] = CheckoutStep::Delivery;
        } elseif ($needsConfiguration) {
            $steps[] = CheckoutStep::Configuration;
        }

        $steps[] = CheckoutStep::Payment;
        $steps[] = CheckoutStep::Review;

        return $steps;
    }

    public function resolve(CartRequirements $requirements, string $current): CheckoutStep
    {
        foreach ($this->stepsFor($requirements) as $step) {
            if ($step->value === $current) {
                return $step;
            }
        }

        return CheckoutStep::Details;
    }

    public function next(CartRequirements $requirements, CheckoutStep $current): ?CheckoutStep
    {
        $steps = $this->stepsFor($requirements);
        foreach ($steps as $index => $step) {
            if ($step === $current) {
                $candidate = $steps[$index + 1] ?? null;
                // Finish is reached after successful payment, not via Continue.
                if ($candidate === CheckoutStep::Review) {
                    return null;
                }

                return $candidate;
            }
        }

        return $steps[0] ?? null;
    }

    public function previous(CartRequirements $requirements, CheckoutStep $current): ?CheckoutStep
    {
        $steps = $this->stepsFor($requirements);
        foreach ($steps as $index => $step) {
            if ($step === $current) {
                return $index > 0 ? $steps[$index - 1] : null;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $completed
     * @return list<CheckoutProgressItem>
     */
    public function progress(CartRequirements $requirements, CheckoutStep $current, array $completed): array
    {
        $steps = $this->stepsFor($requirements);
        $total = count($steps);
        $items = [];

        foreach ($steps as $index => $step) {
            $state = match (true) {
                $step === $current => 'current',
                in_array($step->value, $completed, true) => 'completed',
                default => 'upcoming',
            };

            $items[] = new CheckoutProgressItem($step, $state, $index + 1, $total);
        }

        return $items;
    }

    /**
     * @param  list<string>  $completed
     */
    public function canVisit(CartRequirements $requirements, CheckoutStep $target, array $completed): bool
    {
        // Finish is post-payment only (order confirmation), not reachable from the live checkout form.
        if ($target === CheckoutStep::Review) {
            return false;
        }

        foreach ($this->stepsFor($requirements) as $step) {
            if ($step === $target) {
                return true;
            }
            if (! in_array($step->value, $completed, true)) {
                return false;
            }
        }

        return false;
    }
}
