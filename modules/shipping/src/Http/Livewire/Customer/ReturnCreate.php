<?php

declare(strict_types=1);

namespace Agovena\Modules\Shipping\Http\Livewire\Customer;

use Agovena\Modules\Shipping\ReturnRequestService;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ReturnCreate extends Component
{
    public Order $order;

    public string $reason = '';

    /** @var array<int, int|string> */
    public array $quantities = [];

    public function mount(Order $order): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();

        abort_unless((int) $order->customer_id === (int) $customer->id, 404);

        $this->order = $order->load('items');
    }

    public function submit(ReturnRequestService $returns): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();

        $lines = [];
        foreach ($this->quantities as $itemId => $quantity) {
            if ((int) $quantity > 0) {
                $lines[] = ['order_item_id' => (int) $itemId, 'quantity' => (int) $quantity];
            }
        }

        try {
            $returns->requestFromCustomer($this->order, $lines, $this->reason, $customer);
        } catch (ValidationException $e) {
            $this->addError('quantities', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        session()->flash('status', __('shipping::returns.customer_submitted'));

        $this->redirectRoute('customer.returns');
    }

    public function render(ThemeManager $themes, ReturnRequestService $returns)
    {
        $lines = $returns->eligibleItems($this->order)
            ->map(static fn (OrderItem $item): array => [
                'item' => $item,
                'returnable' => $returns->returnableQuantity($item),
            ])
            ->filter(static fn (array $line): bool => $line['returnable'] > 0)
            ->values();

        $theme = $themes->active();

        return view($theme->view('account.returns.create'), [
            'theme' => $theme,
            'order' => $this->order,
            'lines' => $lines,
            'accountSection' => 'returns',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('shipping::returns.customer_create_title', ['number' => $this->order->number]),
            'theme' => $theme,
        ]);
    }
}
