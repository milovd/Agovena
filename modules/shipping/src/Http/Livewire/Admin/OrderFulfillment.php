<?php

declare(strict_types=1);

namespace Agovena\Modules\Shipping\Http\Livewire\Admin;

use Agovena\Modules\Shipping\Enums\ShipmentStatus;
use Agovena\Modules\Shipping\Models\Shipment;
use Agovena\Modules\Shipping\ShipmentService;
use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class OrderFulfillment extends Component
{
    use AuthorizesRequests;

    public Order $order;

    public string $carrier_name = '';

    public string $tracking_number = '';

    public string $tracking_url = '';

    public function mount(Order $order): void
    {
        $this->authorize('shipping.view');
        $this->order = $order;
    }

    public function markProcessing(int $shipmentId, ShipmentService $shipments): void
    {
        $this->authorize('shipping.manage');
        $shipments->markProcessing($this->findShipment($shipmentId));
        session()->flash('status', __('shipping::admin.saved'));
    }

    public function markShipped(int $shipmentId, ShipmentService $shipments): void
    {
        $this->authorize('shipping.manage');
        try {
            $shipments->markShipped(
                $this->findShipment($shipmentId),
                filled($this->carrier_name) ? $this->carrier_name : null,
                filled($this->tracking_number) ? $this->tracking_number : null,
                filled($this->tracking_url) ? $this->tracking_url : null,
            );
            session()->flash('status', __('shipping::admin.saved'));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['status'][0] ?? $e->getMessage());
        }
    }

    public function markDelivered(int $shipmentId, ShipmentService $shipments): void
    {
        $this->authorize('shipping.manage');
        try {
            $shipments->markDelivered($this->findShipment($shipmentId));
            session()->flash('status', __('shipping::admin.saved'));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['status'][0] ?? $e->getMessage());
        }
    }

    public function cancelShipment(int $shipmentId, ShipmentService $shipments): void
    {
        $this->authorize('shipping.manage');
        try {
            $shipments->cancel($this->findShipment($shipmentId));
            session()->flash('status', __('shipping::admin.saved'));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['status'][0] ?? $e->getMessage());
        }
    }

    public function saveTracking(int $shipmentId, ShipmentService $shipments): void
    {
        $this->authorize('shipping.manage');
        $shipments->updateTracking(
            $this->findShipment($shipmentId),
            filled($this->carrier_name) ? $this->carrier_name : null,
            filled($this->tracking_number) ? $this->tracking_number : null,
            filled($this->tracking_url) ? $this->tracking_url : null,
        );
        session()->flash('status', __('shipping::admin.saved'));
    }

    public function render()
    {
        $shipments = Shipment::query()
            ->with(['items.orderItem'])
            ->where('order_id', $this->order->id)
            ->orderBy('id')
            ->get();

        if ($shipments->isNotEmpty() && $this->carrier_name === '' && $this->tracking_number === '') {
            /** @var Shipment $first */
            $first = $shipments->first();
            $this->carrier_name = (string) ($first->carrier_name ?? '');
            $this->tracking_number = (string) ($first->tracking_number ?? '');
            $this->tracking_url = (string) ($first->tracking_url ?? '');
        }

        return view('livewire.admin.shipping.order-fulfillment', [
            'shipments' => $shipments,
            'statuses' => ShipmentStatus::cases(),
        ]);
    }

    private function findShipment(int $id): Shipment
    {
        return Shipment::query()
            ->where('order_id', $this->order->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
