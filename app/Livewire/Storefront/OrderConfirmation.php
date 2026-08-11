<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Theme\ThemeManager;
use App\Models\Order;
use Livewire\Component;

final class OrderConfirmation extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load(['items', 'payment']);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('checkout.confirmation'), [
            'order' => $this->order,
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => 'Order confirmed',
            'theme' => $theme,
        ]);
    }
}
