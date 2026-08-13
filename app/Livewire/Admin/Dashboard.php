<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\DashboardWidget;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Dashboard extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        $staff = auth()->user();

        $widgets = collect($admin->widgets())->filter(function (DashboardWidget $widget) use ($staff): bool {
            return $widget->permission === null
                || ($staff !== null && $staff->can($widget->permission));
        })->values();

        $productCount = Product::query()->count();
        $activeProductCount = Product::query()->where('status', ProductStatus::Active)->count();
        $orderCount = Order::query()->count();
        $pendingPaymentCount = Payment::query()->where('status', PaymentStatus::Pending)->count();

        $paidRevenueByCurrency = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->groupBy('currency')
            ->selectRaw('currency, COALESCE(SUM(amount), 0) as total')
            ->pluck('total', 'currency')
            ->map(fn (mixed $total): int => (int) $total);

        $recentOrders = Order::query()
            ->with('payment')
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.admin.dashboard', [
            'widgets' => $widgets,
            'productCount' => $productCount,
            'activeProductCount' => $activeProductCount,
            'orderCount' => $orderCount,
            'pendingPaymentCount' => $pendingPaymentCount,
            'paidRevenueByCurrency' => $paidRevenueByCurrency,
            'recentOrders' => $recentOrders,
        ])->layout('layouts.admin', [
            'title' => __('admin.nav.dashboard'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
