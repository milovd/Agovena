<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\UserNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

final class Notifications extends Component
{
    use WithPagination;

    public int $unreadCount = 0;

    public function markRead(int $notificationId): void
    {
        $user = current_user();
        abort_unless($user !== null, 403);

        $user->userNotifications()
            ->whereKey($notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->refreshUnreadCount();
    }

    public function markAllRead(): void
    {
        $user = current_user();
        abort_unless($user !== null, 403);

        $user->userNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->refreshUnreadCount();
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $user = current_user();
        abort_unless($user !== null, 403);

        /** @var LengthAwarePaginator<int, UserNotification> $notifications */
        $notifications = $user->userNotifications()
            ->latest('id')
            ->paginate(20);

        $this->unreadCount = $user->userNotifications()->whereNull('read_at')->count();

        return view($theme->view('account.notifications'), [
            'theme' => $theme,
            'notifications' => $notifications,
            'accountSection' => 'notifications',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.notifications.title'),
            'theme' => $theme,
        ]);
    }

    private function refreshUnreadCount(): void
    {
        $user = current_user();
        $this->unreadCount = $user === null
            ? 0
            : $user->userNotifications()->whereNull('read_at')->count();
    }
}
