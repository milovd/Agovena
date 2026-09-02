<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Referrals;

use App\Agovena\Referrals\ReferralService;
use App\Models\Customer;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public bool $showCodeForm = false;

    public ?int $customerId = null;

    public string $customerSearch = '';

    public string $newCode = '';

    public ?int $maxUses = null;

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

        $data = $this->validate([
            'customerId' => ['required', 'integer', Rule::exists('customers', 'id')],
            'newCode' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9_-]{2,63}$/',
                Rule::unique('referral_codes', 'code'),
            ],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
        ]);

        $customer = Customer::query()->findOrFail($data['customerId']);
        $referrals->createCode(
            $customer,
            $data['newCode'],
            $data['maxUses'],
            $data['expiresAt'] !== null && $data['expiresAt'] !== ''
                ? Carbon::parse($data['expiresAt'])
                : null,
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

    public function render()
    {
        return view('livewire.admin.referrals.index', [
            'attributions' => ReferralAttribution::query()
                ->with(['code', 'order', 'referrer', 'referred'])
                ->latest('id')
                ->limit(100)
                ->get(),
            'codes' => ReferralCode::query()->with('customer')->latest('id')->limit(100)->get(),
            'customers' => $this->showCodeForm && trim($this->customerSearch) !== ''
                ? Customer::query()
                    ->when($this->customerSearch !== '', function ($query): void {
                        $term = '%'.trim($this->customerSearch).'%';
                        $query->where(function ($nested) use ($term): void {
                            $nested->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                    })
                    ->orderBy('name')
                    ->orderBy('id')
                    ->limit(50)
                    ->get(['id', 'name', 'email'])
                : collect(),
            'activeCodeCount' => ReferralCode::query()->where('is_active', true)->count(),
            'reviewCount' => ReferralAttribution::query()->where('status', 'review')->count(),
            'postedRewardCount' => ReferralAttribution::query()->where('status', 'posted')->count(),
        ])->layout('layouts.admin', [
            'title' => __('admin.referrals.title'),
        ]);
    }

    private function resetCodeForm(): void
    {
        $this->reset(['showCodeForm', 'customerId', 'customerSearch', 'newCode', 'maxUses', 'expiresAt']);
        $this->resetValidation();
    }
}
