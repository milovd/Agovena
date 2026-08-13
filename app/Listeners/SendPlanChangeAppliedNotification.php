<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Notifications\SendsCataloguedMail;
use App\Events\PlanChangeApplied;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

final class SendPlanChangeAppliedNotification
{
    public function handle(PlanChangeApplied $event): void
    {
        $request = $event->request->loadMissing(['customer', 'toProduct']);
        $customer = $request->customer;
        if (! $customer instanceof Customer) {
            return;
        }

        $productName = $request->toProduct instanceof Product ? $request->toProduct->name : '';

        app(SendsCataloguedMail::class)->toOrderCustomer(
            $customer->id,
            $customer->email,
            'plan_change_applied',
            [
                'name' => $customer->name,
                'number' => $productName !== '' ? $productName : (string) $request->id,
                'detail' => $productName,
                'action_url' => Route::has('customer.subscriptions') ? route('customer.subscriptions') : url('/'),
                'action_label' => __('notifications.plan_change_applied.action'),
            ],
        );
    }
}
