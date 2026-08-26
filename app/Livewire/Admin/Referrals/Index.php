<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Referrals;

use App\Agovena\Referrals\ReferralService;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('referrals.view');
    }

    public function approve(int $id, ReferralService $referrals): void
    {
        $this->authorize('referrals.manage');
        $attribution = ReferralAttribution::query()->findOrFail($id);
        $referrals->approveReview($attribution);
    }

    public function reject(int $id, ReferralService $referrals): void
    {
        $this->authorize('referrals.manage');
        $attribution = ReferralAttribution::query()->findOrFail($id);
        $referrals->rejectReview($attribution);
    }

    public function deactivateCode(int $id): void
    {
        $this->authorize('referrals.manage');
        ReferralCode::query()->whereKey($id)->update(['is_active' => false]);
    }

    public function activateCode(int $id): void
    {
        $this->authorize('referrals.manage');
        ReferralCode::query()->whereKey($id)->update(['is_active' => true]);
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
        ])->layout('layouts.admin', [
            'title' => __('admin.referrals.title'),
        ]);
    }
}
