<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Media\ProductMedia;
use App\Agovena\Theme\ThemeManager;
use App\Models\Order;
use Livewire\Component;

final class OrderConfirmation extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load(['items.product.capabilities', 'items.product.images', 'payment']);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('checkout.confirmation'), [
            'order' => $this->order,
            'theme' => $theme,
            'fulfillmentCards' => $this->fulfillmentCards(),
            'lineImages' => $this->lineImages(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.confirmation.page_title'),
            'theme' => $theme,
        ]);
    }

    /**
     * @return list<array{key: string, title: string, text: string}>
     */
    private function fulfillmentCards(): array
    {
        $seen = [];
        $cards = [];

        foreach ($this->order->items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }

            foreach ($this->capabilityCardMap() as $capability => $card) {
                if (isset($seen[$capability]) || ! $product->hasCapability($capability)) {
                    continue;
                }
                if ($capability === 'physical' && isset($seen['shippable'])) {
                    continue;
                }
                $seen[$capability] = true;
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return array<string, array{key: string, title: string, text: string}>
     */
    private function capabilityCardMap(): array
    {
        return [
            'shippable' => [
                'key' => 'shippable',
                'title' => __('storefront.confirmation.cards.shipping.title'),
                'text' => __('storefront.confirmation.cards.shipping.text'),
            ],
            'physical' => [
                'key' => 'physical',
                'title' => __('storefront.confirmation.cards.physical.title'),
                'text' => __('storefront.confirmation.cards.physical.text'),
            ],
            'digital' => [
                'key' => 'digital',
                'title' => __('storefront.confirmation.cards.digital.title'),
                'text' => __('storefront.confirmation.cards.digital.text'),
            ],
            'subscribable' => [
                'key' => 'subscribable',
                'title' => __('storefront.confirmation.cards.subscription.title'),
                'text' => __('storefront.confirmation.cards.subscription.text'),
            ],
            'provisionable' => [
                'key' => 'provisionable',
                'title' => __('storefront.confirmation.cards.hosting.title'),
                'text' => __('storefront.confirmation.cards.hosting.text'),
            ],
            'event_ticket' => [
                'key' => 'event_ticket',
                'title' => __('storefront.confirmation.cards.event.title'),
                'text' => __('storefront.confirmation.cards.event.text'),
            ],
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function lineImages(): array
    {
        $images = [];
        foreach ($this->order->items as $item) {
            $images[$item->id] = ProductMedia::primaryUrl($item->product);
        }

        return $images;
    }
}
