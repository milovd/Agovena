<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Confirmation and payment-return pages are not public by sequential order id.
 * Access is granted by an unguessable token (provider extras may append query params),
 * the placing session, or the owning customer.
 */
final class StorefrontOrderAccess
{
    public const SESSION_KEY = 'agovena.storefront_orders';

    public const QUERY_KEY = 'access';

    public function remember(Order $order): void
    {
        $ids = array_map('intval', session()->get(self::SESSION_KEY, []));
        $ids[] = (int) $order->id;
        session()->put(self::SESSION_KEY, array_values(array_unique($ids)));
    }

    public function authorize(Request $request, Order $order): void
    {
        abort_unless($this->allows($request, $order), 404);
    }

    public function allows(Request $request, Order $order): bool
    {
        if ($this->tokenMatches($request, $order)) {
            $this->remember($order);

            return true;
        }

        if ($this->inSession($order)) {
            return true;
        }

        $user = $request->user();
        if ($user instanceof User && $order->customer_id !== null) {
            $customer = $user->customer;
            if ($customer !== null && (int) $order->customer_id === (int) $customer->id) {
                $this->remember($order);

                return true;
            }
        }

        return false;
    }

    public function paymentStatusUrl(Order $order): string
    {
        return route('storefront.payment.status', [
            'order' => $order,
            self::QUERY_KEY => $this->token($order),
        ]);
    }

    public function confirmationUrl(Order $order): string
    {
        return route('storefront.order.confirmation', [
            'order' => $order,
            self::QUERY_KEY => $this->token($order),
        ]);
    }

    public function token(Order $order): string
    {
        if (filled($order->storefront_token)) {
            return (string) $order->storefront_token;
        }

        $order->forceFill(['storefront_token' => Order::generateStorefrontToken()])->save();

        return (string) $order->storefront_token;
    }

    private function tokenMatches(Request $request, Order $order): bool
    {
        $provided = (string) $request->query(self::QUERY_KEY, '');
        $expected = (string) ($order->storefront_token ?? '');

        return $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }

    private function inSession(Order $order): bool
    {
        $ids = array_map('intval', session()->get(self::SESSION_KEY, []));

        return in_array((int) $order->id, $ids, true);
    }
}
