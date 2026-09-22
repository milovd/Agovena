<?php

declare(strict_types=1);

namespace App\Agovena\Physical\Http\Livewire\Admin;

use App\Agovena\Physical\Models\ReturnRequest;
use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class OrderReturns extends Component
{
    use AuthorizesRequests;

    public Order $order;

    public function mount(Order $order): void
    {
        $this->authorize('returns.view');
        $this->order = $order;
    }

    public function render()
    {
        return view('livewire.admin.shipping.order-returns', [
            'returns' => ReturnRequest::query()
                ->with('items.orderItem')
                ->where('order_id', $this->order->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
