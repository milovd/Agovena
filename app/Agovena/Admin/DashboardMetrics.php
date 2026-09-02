<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Money\CurrencyConverter;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Ticket;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DashboardMetrics
{
    public function __construct(
        private readonly ModuleManager $modules,
    ) {}

    /**
     * @return array{
     *     metrics: list<array{id: string, label: string, value: string, hint: ?string, href: ?string}>,
     *     revenueSeries: array{labels: list<string>, values: list<int>, currency: string},
     *     orderSeries: array{labels: list<string>, values: list<int>},
     *     recentOrders: \Illuminate\Database\Eloquent\Collection<int, Order>,
     *     attention: list<array{label: string, href: string, count: int}>,
     *     productCount: int,
     *     activeProductCount: int,
     *     orderCount: int,
     *     pendingPaymentCount: int,
     *     paidRevenueByCurrency: Collection<string, int>
     * }
     */
    public function build(string $chartRange = '14'): array
    {
        ['from' => $from, 'days' => $days] = $this->chartWindow($chartRange);

        $productCount = Product::query()->count();
        $activeProductCount = Product::query()->where('status', ProductStatus::Active)->count();
        $orderCount = Order::query()->count();
        $customerCount = Customer::query()->count();
        $pendingPaymentCount = Payment::query()->where('status', PaymentStatus::Pending)->count();
        $openTicketCount = Ticket::query()->where('status', '!=', TicketStatus::Closed)->count();

        $paidRevenueByCurrency = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->groupBy('currency')
            ->selectRaw('currency, COALESCE(SUM(amount), 0) as total')
            ->pluck('total', 'currency')
            ->map(fn (mixed $total): int => (int) $total);

        $displayCurrency = MoneyFormatter::preferredDisplayCurrency();
        $paidRevenue = $this->sumConverted($paidRevenueByCurrency, $displayCurrency);
        $paidOrderCount = Order::query()
            ->where('status', OrderStatus::Paid)
            ->count();
        $aov = $paidOrderCount > 0 ? (int) floor($paidRevenue / $paidOrderCount) : 0;

        $metrics = [
            [
                'id' => 'revenue',
                'label' => (string) __('admin.dashboard.stats.paid_revenue'),
                'value' => $paidRevenueByCurrency->isEmpty()
                    ? (string) __('common.em_dash')
                    : MoneyFormatter::format($paidRevenue, $displayCurrency),
                'hint' => $paidRevenueByCurrency->isEmpty()
                    ? (string) __('admin.dashboard.stats.no_paid_payments')
                    : (string) __('admin.dashboard.stats.paid_payments_sum'),
                'href' => auth()->user()?->can('orders.view') ? route('admin.orders.index') : null,
            ],
            [
                'id' => 'orders',
                'label' => (string) __('admin.dashboard.stats.orders'),
                'value' => number_format($orderCount),
                'hint' => null,
                'href' => auth()->user()?->can('orders.view') ? route('admin.orders.index') : null,
            ],
            [
                'id' => 'customers',
                'label' => (string) __('admin.dashboard.stats.customers'),
                'value' => number_format($customerCount),
                'hint' => null,
                'href' => auth()->user()?->can('customers.view') ? route('admin.customers.index') : null,
            ],
            [
                'id' => 'aov',
                'label' => (string) __('admin.dashboard.stats.aov'),
                'value' => $paidOrderCount > 0
                    ? MoneyFormatter::format($aov, $displayCurrency)
                    : (string) __('common.em_dash'),
                'hint' => (string) __('admin.dashboard.stats.aov_hint'),
                'href' => null,
            ],
            [
                'id' => 'products',
                'label' => (string) __('admin.dashboard.stats.products'),
                'value' => number_format($productCount),
                'hint' => (string) __('admin.dashboard.stats.products_active', ['count' => $activeProductCount]),
                'href' => auth()->user()?->can('products.view') ? route('admin.products.index') : null,
            ],
        ];

        $activeServices = $this->modules->isEnabled('provisioning') && Schema::hasTable('service_instances')
            ? DB::table('service_instances')->where('status', 'active')->count()
            : null;
        $metrics[] = [
            'id' => 'services',
            'label' => (string) __('admin.dashboard.stats.active_services'),
            'value' => $activeServices === null ? (string) __('common.em_dash') : number_format($activeServices),
            'hint' => $activeServices === null ? (string) __('admin.dashboard.stats.services_unavailable') : null,
            'href' => $activeServices !== null && auth()->user()?->can('provisioning.view')
                ? route('admin.provisioning.index')
                : null,
        ];

        $attention = [];
        if ($pendingPaymentCount > 0 && auth()->user()?->can('orders.view')) {
            $attention[] = [
                'label' => (string) trans_choice('admin.dashboard.attention.pending_payments', $pendingPaymentCount, ['count' => $pendingPaymentCount]),
                'href' => route('admin.orders.index'),
                'count' => $pendingPaymentCount,
            ];
        }
        if ($openTicketCount > 0 && auth()->user()?->can('tickets.view')) {
            $attention[] = [
                'label' => (string) trans_choice('admin.dashboard.attention.open_tickets', $openTicketCount, ['count' => $openTicketCount]),
                'href' => route('admin.tickets.index'),
                'count' => $openTicketCount,
            ];
        }
        if ($productCount === 0 && auth()->user()?->can('products.create')) {
            $attention[] = [
                'label' => (string) __('admin.dashboard.attention.no_products'),
                'href' => route('admin.products.create'),
                'count' => 0,
            ];
        }

        return [
            'metrics' => $metrics,
            'revenueSeries' => $this->dailyPaidRevenue($from, $days, $displayCurrency),
            'orderSeries' => $this->dailyOrderCounts($from, $days),
            'recentOrders' => Order::query()->with('payment')->latest('id')->limit(8)->get(),
            'attention' => $attention,
            'productCount' => $productCount,
            'activeProductCount' => $activeProductCount,
            'orderCount' => $orderCount,
            'pendingPaymentCount' => $pendingPaymentCount,
            'paidRevenueByCurrency' => $paidRevenueByCurrency,
        ];
    }

    /**
     * @param  Collection<string, int>  $totalsByCurrency
     */
    private function sumConverted(Collection $totalsByCurrency, string $displayCurrency): int
    {
        $sum = 0;

        try {
            $converter = app(CurrencyConverter::class);
        } catch (Throwable) {
            return (int) $totalsByCurrency->get($displayCurrency, $totalsByCurrency->first() ?? 0);
        }

        foreach ($totalsByCurrency as $currency => $amount) {
            try {
                $sum += $converter->convert((int) $amount, (string) $currency, $displayCurrency);
            } catch (Throwable) {
                if (strtoupper((string) $currency) === strtoupper($displayCurrency)) {
                    $sum += (int) $amount;
                }
            }
        }

        return $sum;
    }

    /**
     * @return array{labels: list<string>, values: list<int>, currency: string}
     */
    private function dailyPaidRevenue(CarbonImmutable $from, int $days, string $displayCurrency): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', paid_at)"
            : 'DATE(paid_at)';

        $rows = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->selectRaw("{$dateExpr} as day, currency, COALESCE(SUM(amount), 0) as total")
            ->groupBy('day', 'currency')
            ->get();

        $byDay = collect();
        foreach ($rows as $row) {
            $day = (string) $row->getAttribute('day');
            $converted = $this->sumConverted(
                collect([(string) $row->getAttribute('currency') => (int) $row->getAttribute('total')]),
                $displayCurrency,
            );
            $byDay[$day] = (int) ($byDay[$day] ?? 0) + $converted;
        }

        return $this->fillDailySeries($from, $days, $byDay, $displayCurrency);
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function dailyOrderCounts(CarbonImmutable $from, int $days): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $rows = Order::query()
            ->where('created_at', '>=', $from)
            ->selectRaw("{$dateExpr} as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total);

        $series = $this->fillDailySeries($from, $days, $rows, 'EUR');

        return [
            'labels' => $series['labels'],
            'values' => $series['values'],
        ];
    }

    /**
     * @param  Collection<string, int>  $rows
     * @return array{labels: list<string>, values: list<int>, currency: string}
     */
    private function fillDailySeries(CarbonImmutable $from, int $days, Collection $rows, string $currency): array
    {
        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'currency' => $currency,
        ];
    }

    /**
     * @return array{from: CarbonImmutable, days: int}
     */
    private function chartWindow(string $range): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $from = match ($range) {
            '7' => $today->subDays(6),
            'month' => $today->startOfMonth(),
            '90' => $today->subDays(89),
            default => $today->subDays(13),
        };

        return [
            'from' => $from,
            'days' => (int) $from->diffInDays($today) + 1,
        ];
    }
}
