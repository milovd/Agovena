<?php

declare(strict_types=1);

namespace App\Agovena\Physical\Listeners;

use App\Agovena\Physical\Enums\ShipmentStatus;
use App\Agovena\Physical\Models\Shipment;
use App\Agovena\Physical\ShipmentService;
use App\Events\OrderCancelled;

final class CancelShipmentsWhenOrderCancelled
{
    public function __construct(
        private readonly ShipmentService $shipments,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $pending = Shipment::query()
            ->where('order_id', $event->order->id)
            ->whereIn('status', [ShipmentStatus::Pending, ShipmentStatus::Processing])
            ->get();

        foreach ($pending as $shipment) {
            $this->shipments->cancel($shipment);
        }
    }
}
