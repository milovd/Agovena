<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Admin\OrderDetailSection;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Invoices\LinkInvoiceToOrder;
use App\Agovena\Invoices\UnlinkInvoiceFromOrder;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\DeleteOrder;
use App\Agovena\Orders\Lines\OrderLineDraft;
use App\Agovena\Orders\SaveOrderChanges;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Orders\UnpaidOrderEditGuard;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Recurring\Enums\SubscriptionInterval;
use App\Agovena\Recurring\Models\Subscription;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Livewire\Forms\Admin\OrderDetailsForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Order $order;

    public string $reference = '';

    public bool $confirmingPayment = false;

    public bool $confirmingCancel = false;

    public string $invoiceSearch = '';

    public OrderDetailsForm $details;

    public ?int $customerId = null;

    public string $customerLabel = '';

    /** @var list<array{key: string, id: int|null, product_id: int|null, label: string, quantity: string, price: string}> */
    public array $lines = [];

    /** The open search select: "customer" or "line.{index}". */
    public ?string $picker = null;

    public string $pickerTerm = '';

    public string $pristine = '';

    public string $pristineLines = '';

    public bool $confirmingDelete = false;

    public function mount(Order $order): void
    {
        $this->authorize('orders.view');
        $this->order = $order->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds', 'customer']);
        $this->fillForm();
    }

    public function openPicker(string $field): void
    {
        $this->authorize('orders.update');
        abort_unless($field === 'customer' || $this->lineIndex($field) !== null, 404);
        $this->picker = $field;
        $this->pickerTerm = '';
    }

    public function closePicker(): void
    {
        $this->picker = null;
        $this->pickerTerm = '';
    }

    public function pick(string $field, int $id): void
    {
        $this->authorize('orders.update');
        $guard = app(UnpaidOrderEditGuard::class);

        if ($field === 'customer') {
            $reason = $guard->blockingReason($this->order, forCustomerChange: true);
            if ($reason !== null) {
                $this->addError('customer', $reason);

                return;
            }
            $customer = Customer::query()->findOrFail($id);
            $this->customerId = (int) $customer->id;
            $this->customerLabel = $this->labelFor($customer);
            $this->details->customerName = (string) $customer->name;
            $this->details->customerEmail = (string) $customer->email;
            $this->closePicker();

            return;
        }

        $index = $this->lineIndex($field);
        abort_if($index === null, 404);
        $reason = $guard->blockingReason($this->order, forLineChange: true);
        if ($reason !== null) {
            $this->addError('lines', $reason);

            return;
        }

        $product = Product::query()->findOrFail($id);
        $this->lines[$index]['product_id'] = (int) $product->id;
        $this->lines[$index]['label'] = (string) $product->name;
        try {
            $price = app(ProductOptionPricer::class)->unitPrice($product, [], (string) $this->order->currency);
            $this->lines[$index]['price'] = MoneyFormatter::majorInputFromMinor($price->amount, (string) $this->order->currency);
        } catch (InvalidArgumentException) {
            // Not priced in this currency: staff enter the price.
        }
        $this->resetValidation('lines.'.$index.'.product');
        $this->closePicker();
    }

    public function addLine(): void
    {
        $this->authorize('orders.update');
        $reason = app(UnpaidOrderEditGuard::class)->blockingReason($this->order, forLineChange: true);
        if ($reason !== null) {
            $this->addError('lines', $reason);

            return;
        }

        $this->lines[] = [
            'key' => 'new-'.Str::lower(Str::random(8)),
            'id' => null,
            'product_id' => null,
            'label' => '',
            'quantity' => '1',
            'price' => MoneyFormatter::majorInputFromMinor(0, (string) $this->order->currency),
        ];
        $this->openPicker('line.'.(count($this->lines) - 1));
    }

    public function removeLine(int $index): void
    {
        $this->authorize('orders.update');
        abort_unless(isset($this->lines[$index]), 404);
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->closePicker();
    }

    public function resetForm(): void
    {
        $this->fillForm();
    }

    public function saveOrder(SaveOrderChanges $save, UnpaidOrderEditGuard $guard): void
    {
        $this->authorize('orders.update');
        $this->closePicker();

        $attributes = array_merge($this->details->detailsAttributes(), $this->details->addressAttributes());
        // Lines only go along when they changed, so contact edits never trip over line rules.
        $drafts = $this->linesHash() !== $this->pristineLines && $guard->blockingReason($this->order, forLineChange: true) === null
            ? $this->lineDrafts()
            : null;

        $customer = null;
        if ($this->customerId !== null && $this->customerId !== (int) $this->order->customer_id) {
            $customer = Customer::query()->findOrFail($this->customerId);
            if (! $this->requireRecentPassword('saveOrder')) {
                return;
            }
        }

        $openInvoice = $this->order->invoices()->where('status', InvoiceStatus::Issued)->value('id');

        try {
            $save->handle($this->order, $this->staff(), $customer, $attributes, $drafts);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            foreach (['order', 'lines', 'customer'] as $key) {
                if (isset($errors[$key][0])) {
                    $this->addError($key, $errors[$key][0]);
                }
            }
            if (! isset($errors['order']) && ! isset($errors['lines']) && ! isset($errors['customer'])) {
                $this->addError('order', (string) collect($errors)->flatten()->first());
            }

            return;
        }

        $this->refreshOrder();
        $reissued = $openInvoice !== null
            && $this->order->invoices()->where('status', InvoiceStatus::Issued)->value('id') !== $openInvoice;
        $this->fillForm();
        session()->flash('status', __($reissued ? 'admin.orders.inline.saved_reissued' : 'admin.orders.inline.saved'));
    }

    public function confirmDelete(): void
    {
        $this->authorize('orders.delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteOrder(DeleteOrder $delete): void
    {
        $this->authorize('orders.delete');
        if (! $this->requireRecentPassword('deleteOrder')) {
            return;
        }

        try {
            $delete->handle($this->order, $this->staff());
        } catch (ValidationException $exception) {
            $this->confirmingDelete = false;
            session()->flash('error', $exception->errors()['order'][0] ?? $exception->getMessage());

            return;
        }

        session()->flash('status', __('admin.orders.flash.deleted'));
        $this->redirect(route('admin.orders.index'), navigate: true);
    }

    public function startRecordPayment(): void
    {
        $this->authorize('payments.record');
        $this->confirmingPayment = true;
    }

    public function cancelRecordPayment(): void
    {
        $this->confirmingPayment = false;
    }

    public function startCancelUnpaid(): void
    {
        $this->authorizeCancelUnpaid();
        $this->confirmingCancel = true;
    }

    public function abortCancelUnpaid(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelUnpaid(CancelUnpaidOrder $cancel): void
    {
        $this->authorizeCancelUnpaid();

        if (! $this->requireRecentPassword('cancelUnpaid')) {
            return;
        }

        /** @var User $staff */
        $staff = Auth::user();

        $this->order = $cancel->handle($this->order, UnpaidOrderCancelSource::Staff, $staff)
            ->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds']);
        $this->confirmingCancel = false;
        session()->flash('status', __('admin.orders.flash.cancelled'));
    }

    public function recordPayment(RecordManualPayment $action): void
    {
        $this->authorize('payments.record');

        if (! $this->requireRecentPassword('recordPayment')) {
            return;
        }

        /** @var User $staff */
        $staff = Auth::user();

        $action->handle(
            $this->order,
            $staff,
            filled($this->reference) ? $this->reference : null,
        );

        $this->order->refresh()->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds']);
        $this->confirmingPayment = false;
        session()->flash('status', __('admin.orders.flash.payment_recorded'));
    }

    public function linkInvoice(int $invoiceId, LinkInvoiceToOrder $link): void
    {
        $this->authorize('invoices.manage');

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        /** @var User $staff */
        $staff = Auth::user();
        $link->handle($invoice, $this->order, $staff);
        $this->refreshOrder();
        $this->invoiceSearch = '';
        session()->flash('status', __('admin.orders.flash.invoice_linked'));
    }

    public function unlinkInvoice(int $invoiceId, UnlinkInvoiceFromOrder $unlink): void
    {
        $this->authorize('invoices.manage');

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        abort_unless((int) $invoice->order_id === (int) $this->order->id, 404);
        /** @var User $staff */
        $staff = Auth::user();
        $unlink->handle($invoice, $staff);
        $this->refreshOrder();
        session()->flash('status', __('admin.orders.flash.invoice_unlinked'));
    }

    public function render(AdminRegistrar $admin)
    {
        $this->authorize('orders.view');
        /** @var InMemoryAdminRegistrar $admin */
        $user = Auth::user();
        $canRecord = $user?->can('payments.record') === true
            && $this->order->payment?->status === PaymentStatus::Pending;
        $canCancelUnpaid = $this->order->canCancelUnpaid()
            && ($user?->can('orders.cancel') === true || $user?->can('invoices.void') === true);
        $canManageInvoices = $user?->can('invoices.manage') === true
            && $this->order->status === OrderStatus::Pending
            && ($this->order->payment === null || $this->order->payment->status === PaymentStatus::Pending)
            && ! $this->order->invoices()->where(function ($query): void {
                $query->where('status', '!=', InvoiceStatus::Issued)
                    ->orWhereNotNull('paid_at')
                    ->orWhereHas('creditNotes')
                    ->orWhereHas('refunds');
            })->exists();
        $invoiceCandidates = collect();
        if (trim($this->invoiceSearch) !== '' && $canManageInvoices) {
            $term = trim($this->invoiceSearch);
            $invoiceCandidates = Invoice::query()
                ->whereNull('order_id')
                ->where('status', InvoiceStatus::Issued)
                ->where(function ($query) use ($term): void {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                    $query->where('number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like);
                })
                ->latest('id')
                ->limit(8)
                ->get();
        }

        return view('livewire.admin.orders.show', [
            'canRecord' => $canRecord,
            'canUpdate' => $user?->can('orders.update') === true,
            'canDelete' => $user?->can('orders.delete') === true,
            'customerLockReason' => app(UnpaidOrderEditGuard::class)->blockingReason($this->order, forCustomerChange: true),
            'linesLockReason' => app(UnpaidOrderEditGuard::class)->blockingReason($this->order, forLineChange: true),
            'pickerResults' => $this->pickerResults(),
            'lineRecurring' => $this->lineRecurring(),
            'isDirty' => $this->pristine !== $this->formHash(),
            'canViewSubscriptions' => $user?->can('subscriptions.view') === true,
            'displayableProperties' => array_values(array_filter(
                $this->order->custom_properties_snapshot ?? [],
                static fn (mixed $property): bool => is_array($property)
                    && is_scalar($property['label'] ?? $property['key'] ?? null)
                    && is_scalar($property['value'] ?? null),
            )),
            'canCancelUnpaid' => $canCancelUnpaid,
            'canManageInvoices' => $canManageInvoices,
            'invoiceCandidates' => $invoiceCandidates,
            'orderDetailSections' => array_values(array_filter(
                $admin->orderDetailSections(),
                fn (OrderDetailSection $section): bool => $section->permission === null
                    || (auth()->user()?->can($section->permission) ?? false),
            )),
            'navigation' => $admin->navigationItems(),
            'paymentGatewayLabel' => $this->paymentGatewayLabel(),
        ])->layout('layouts.admin', [
            'title' => __('admin.orders.show.title', ['number' => $this->order->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function paymentGatewayLabel(): string
    {
        $method = $this->order->payment?->method;
        if (! is_string($method) || $method === '') {
            return '-';
        }

        $gateway = app(PaymentGatewayRegistry::class)->get($method);
        if ($gateway !== null) {
            return __($gateway->label());
        }

        $key = 'admin.orders.method.'.$method;

        return Lang::has($key) ? __($key) : $method;
    }

    private function authorizeCancelUnpaid(): void
    {
        $user = Auth::user();
        abort_unless(
            $user instanceof User
                && ($user->can('orders.cancel') || $user->can('invoices.void')),
            403,
        );
    }

    private function refreshOrder(): void
    {
        $this->order = Order::query()->whereKey($this->order->id)->firstOrFail()
            ->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds', 'customer']);
    }

    private function fillForm(): void
    {
        $this->details->fillFrom($this->order);
        $this->customerId = $this->order->customer_id === null ? null : (int) $this->order->customer_id;
        $this->customerLabel = $this->order->customer !== null
            ? $this->labelFor($this->order->customer)
            : (string) $this->order->customer_name.' ('.$this->order->customer_email.')';
        $currency = (string) $this->order->currency;
        $this->lines = $this->order->items->map(static fn (OrderItem $item): array => [
            'key' => 'item-'.$item->id,
            'id' => (int) $item->id,
            'product_id' => $item->product_id === null ? null : (int) $item->product_id,
            'label' => (string) $item->label,
            'quantity' => (string) $item->quantity,
            'price' => MoneyFormatter::majorInputFromMinor((int) $item->unit_amount, $currency),
        ])->values()->all();
        $this->closePicker();
        $this->resetValidation();
        $this->pristine = $this->formHash();
        $this->pristineLines = $this->linesHash();
    }

    private function formHash(): string
    {
        return md5((string) json_encode([$this->details->all(), $this->customerId, $this->linesHash()]));
    }

    private function linesHash(): string
    {
        return md5((string) json_encode(array_map(
            static fn (array $line): array => [$line['id'], $line['product_id'], trim($line['quantity']), trim($line['price'])],
            $this->lines,
        )));
    }

    /** @return list<OrderLineDraft> */
    private function lineDrafts(): array
    {
        $currency = (string) $this->order->currency;
        $errors = [];
        $drafts = [];

        foreach ($this->lines as $index => $line) {
            if ($line['product_id'] === null) {
                $errors['lines.'.$index.'.product'] = __('admin.orders.lines.errors.product');
            }
            $quantity = trim($line['quantity']);
            if (! ctype_digit($quantity) || (int) $quantity < 1 || (int) $quantity > 10000) {
                $errors['lines.'.$index.'.quantity'] = __('admin.orders.lines.errors.quantity');
            }
            try {
                $unit = MoneyFormatter::minorFromMajorInput(trim($line['price']), $currency);
            } catch (InvalidArgumentException) {
                $unit = null;
                $errors['lines.'.$index.'.price'] = __('admin.orders.lines.errors.price');
            }

            if ($line['product_id'] !== null && $unit !== null && ctype_digit($quantity)) {
                $drafts[] = new OrderLineDraft($line['id'], (int) $line['product_id'], (int) $quantity, $unit);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $drafts;
    }

    private function lineIndex(string $field): ?int
    {
        if (! str_starts_with($field, 'line.')) {
            return null;
        }
        $index = substr($field, 5);

        return ctype_digit($index) && isset($this->lines[(int) $index]) ? (int) $index : null;
    }

    /** @return list<array{id: int, label: string, hint: string|null}> */
    private function pickerResults(): array
    {
        if ($this->picker === null) {
            return [];
        }

        $term = trim($this->pickerTerm);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        if ($this->picker === 'customer') {
            return Customer::query()
                ->when($term !== '', static function ($query) use ($like): void {
                    $query->where(static function ($query) use ($like): void {
                        $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    });
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'email'])
                ->map(fn (Customer $customer): array => ['id' => (int) $customer->id, 'label' => $this->labelFor($customer), 'hint' => null])
                ->all();
        }

        return Product::query()
            ->when($term !== '', static function ($query) use ($like): void {
                $query->where(static function ($query) use ($like): void {
                    $query->where('name', 'like', $like)->orWhere('sku', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sku', 'price_amount', 'currency'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'label' => (string) $product->name,
                'hint' => MoneyFormatter::format((int) $product->price_amount, (string) $product->currency),
            ])
            ->all();
    }

    /**
     * How each line bills again: the subscription it created, or the recurring setup of its product.
     *
     * @return array<string, array{count: int, interval: string, subscription: Subscription|null}>
     */
    private function lineRecurring(): array
    {
        $subscriptions = Subscription::query()
            ->where('order_id', $this->order->id)
            ->whereNotNull('order_item_id')
            ->get()
            ->keyBy('order_item_id');
        $productIds = array_values(array_filter(array_column($this->lines, 'product_id')));
        $products = Product::query()->with('capabilities')->whereIn('id', $productIds)->get()->keyBy('id');

        $recurring = [];
        foreach ($this->lines as $line) {
            $subscription = $line['id'] !== null ? $subscriptions->get($line['id']) : null;
            if ($subscription instanceof Subscription) {
                $recurring[$line['key']] = [
                    'count' => (int) $subscription->interval_count,
                    'interval' => $subscription->interval->value,
                    'subscription' => $subscription,
                ];

                continue;
            }

            $config = $line['product_id'] !== null
                ? $products->get($line['product_id'])?->capability('subscribable')?->config
                : null;
            if (is_array($config)) {
                $interval = SubscriptionInterval::tryFrom((string) ($config['interval'] ?? 'month')) ?? SubscriptionInterval::Month;
                $recurring[$line['key']] = [
                    'count' => max(1, (int) ($config['interval_count'] ?? 1)),
                    'interval' => $interval->value,
                    'subscription' => null,
                ];
            }
        }

        return $recurring;
    }

    private function labelFor(Customer $customer): string
    {
        return $customer->name.' ('.$customer->email.')';
    }

    private function staff(): User
    {
        $staff = Auth::user();
        abort_unless($staff instanceof User, 403);

        return $staff;
    }
}
