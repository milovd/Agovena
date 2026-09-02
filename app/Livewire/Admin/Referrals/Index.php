<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Referrals;

use App\Agovena\Referrals\ReferralService;
use App\Models\Customer;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use App\Models\ReferralVisit;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public bool $showCodeForm = false;

    public ?int $editingCodeId = null;

    public ?int $customerId = null;

    public string $customerSearch = '';

    public string $newCode = '';

    public ?int $maxUses = null;

    public ?int $rewardPercentage = null;

    public ?int $windowDays = null;

    public string $expiresAt = '';

    public function mount(): void
    {
        $this->authorize('referrals.view');
    }

    public function approve(int $id, ReferralService $referrals): void
    {
        $this->authorize('referrals.manage');
        $attribution = ReferralAttribution::query()->findOrFail($id);
        $referrals->approveReview($attribution);
        session()->flash('status', __('admin.referrals.approved'));
    }

    public function reject(int $id, ReferralService $referrals): void
    {
        $this->authorize('referrals.manage');
        $attribution = ReferralAttribution::query()->findOrFail($id);
        $referrals->rejectReview($attribution);
        session()->flash('status', __('admin.referrals.rejected'));
    }

    public function createCode(): void
    {
        $this->authorize('referrals.manage');
        $this->resetCodeForm();
        $this->showCodeForm = true;
    }

    public function editCode(int $id): void
    {
        $this->authorize('referrals.manage');
        $code = ReferralCode::query()->findOrFail($id);

        $this->editingCodeId = $code->id;
        $this->customerId = $code->customer_id;
        $this->customerSearch = $code->customer?->name.' ('.$code->customer?->email.')';
        $this->newCode = $code->code;
        $this->maxUses = $code->max_uses;
        $this->rewardPercentage = $code->reward_percentage;
        $this->windowDays = $code->window_days;
        $this->expiresAt = $code->expires_at?->format('Y-m-d\\TH:i') ?? '';
        $this->showCodeForm = true;
    }

    public function selectCustomer(int $id): void
    {
        $this->authorize('referrals.manage');
        $customer = Customer::query()->findOrFail($id);

        $this->customerSearch = $customer->name.' ('.$customer->email.')';
        $this->customerId = $customer->id;
        $this->resetValidation('customerId');
    }

    public function updatedCustomerSearch(): void
    {
        $this->customerId = null;
    }

    public function saveCode(ReferralService $referrals): void
    {
        $this->authorize('referrals.manage');
        $this->newCode = strtoupper(trim($this->newCode));
        $codeRule = Rule::unique('referral_codes', 'code');
        if ($this->editingCodeId !== null) {
            $codeRule->ignore($this->editingCodeId);
        }

        $data = $this->validate([
            'customerId' => ['required', 'integer', Rule::exists('customers', 'id')],
            'newCode' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9_-]{2,63}$/',
                $codeRule,
            ],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
            'rewardPercentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'windowDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $expiresAt = $data['expiresAt'] !== null && $data['expiresAt'] !== ''
            ? Carbon::parse($data['expiresAt'])
            : null;

        if ($this->editingCodeId !== null) {
            $code = ReferralCode::query()->findOrFail($this->editingCodeId);
            $referrals->updateCode($code, $data['maxUses'], $expiresAt, $data['rewardPercentage'], $data['windowDays']);
            session()->flash('status', __('admin.referrals.updated'));
            $this->resetCodeForm();

            return;
        }

        $customer = Customer::query()->findOrFail($data['customerId']);
        $referrals->createCode(
            $customer,
            $data['newCode'],
            $data['maxUses'],
            $expiresAt,
            $data['rewardPercentage'],
            $data['windowDays'],
        );

        session()->flash('status', __('admin.referrals.created'));
        $this->resetCodeForm();
    }

    public function cancelCode(): void
    {
        $this->resetCodeForm();
    }

    public function deactivateCode(int $id): void
    {
        $this->authorize('referrals.manage');
        ReferralCode::query()->whereKey($id)->update(['is_active' => false]);
        session()->flash('status', __('admin.referrals.deactivated'));
    }

    public function activateCode(int $id): void
    {
        $this->authorize('referrals.manage');
        ReferralCode::query()->whereKey($id)->update(['is_active' => true]);
        session()->flash('status', __('admin.referrals.activated'));
    }

    public function render(ReferralService $referrals)
    {
        return view('livewire.admin.referrals.index', [
            'attributions' => ReferralAttribution::query()
                ->with(['code', 'order', 'referrer', 'referred'])
                ->latest('id')
                ->limit(100)
                ->get(),
            'codes' => ReferralCode::query()
                ->with('customer')
                ->withCount('visits')
                ->withSum('visits', 'clicks_count')
                ->withCount([
                    'attributions as paid_purchases_count' => static fn ($query) => $query->where(static function ($query): void {
                        $query->whereNotNull('purchased_at')->orWhere('status', 'posted');
                    }),
                ])
                ->latest('id')
                ->limit(100)
                ->get(),
            'customers' => $this->showCodeForm && trim($this->customerSearch) !== ''
                ? Customer::query()
                    ->where(function ($query): void {
                        $term = '%'.trim($this->customerSearch).'%';
                        $query->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    })
                    ->orderBy('name')
                    ->orderBy('id')
                    ->limit(50)
                    ->get(['id', 'name', 'email'])
                : collect(),
            'activeCodeCount' => ReferralCode::query()->where('is_active', true)->count(),
            'reviewCount' => ReferralAttribution::query()->where('status', 'review')->count(),
            'postedRewardCount' => ReferralAttribution::query()->where('status', 'posted')->count(),
            'linkClicks' => (int) ReferralVisit::query()->sum('clicks_count'),
            'uniqueVisitors' => ReferralVisit::query()->count(),
            'paidPurchases' => ReferralAttribution::query()
                ->where(static function ($query): void {
                    $query->whereNotNull('purchased_at')->orWhere('status', 'posted');
                })
                ->count(),
            'defaultRewardPercentage' => $referrals->defaultRewardPercentage(),
            'defaultWindowDays' => $referrals->defaultWindowDays(),
        ])->layout('layouts.admin', [
            'title' => __('admin.referrals.title'),
        ]);
    }

    private function resetCodeForm(): void
    {
        $this->reset(['showCodeForm', 'editingCodeId', 'customerId', 'customerSearch', 'newCode', 'maxUses', 'rewardPercentage', 'windowDays', 'expiresAt']);
        $this->resetValidation();
    }
}
