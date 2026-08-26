<?php

declare(strict_types=1);

use App\Livewire\Customer\Account\Notifications;
use App\Livewire\Customer\Account\NotificationSettings;
use App\Models\NotificationTemplate;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('shows an authenticated customer notification count on the account menu', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    UserNotification::query()->create([
        'user_id' => $user->id,
        'key' => 'payment_recorded',
        'title' => 'Payment received',
        'body' => 'Your payment was recorded.',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('store-header__account-count', false)
        ->assertSee('store-account-menu__item--notifications', false)
        ->assertSee('store-account-menu__notification-count', false)
        ->assertSee(route('customer.notifications'), false)
        ->assertDontSee('store-notification-bell', false)
        ->assertSee('1', false)
        ->assertSee(__('customer.notifications.title'), false);
});

it('does not expose customer notifications to guests', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('store-notification-bell', false)
        ->assertDontSee(__('customer.notifications.title'), false);

    $this->get(route('customer.notifications'))
        ->assertRedirect(route('login'));
});

it('scopes the customer notification center and supports read actions', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $notification = UserNotification::query()->create([
        'user_id' => $user->id,
        'key' => 'order_placed',
        'title' => 'Order placed',
        'body' => 'Your order was received.',
    ]);
    UserNotification::query()->create([
        'user_id' => $otherUser->id,
        'key' => 'order_placed',
        'title' => 'Private message',
        'body' => 'This belongs to another user.',
    ]);

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->assertSee('Order placed')
        ->assertDontSee('Private message')
        ->call('markRead', $notification->id)
        ->assertSet('unreadCount', 0);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('renders loading and offline states in the customer notification center', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('customer.notifications'))
        ->assertOk()
        ->assertSee(__('customer.notifications.loading'))
        ->assertSee(__('customer.notifications.offline'));
});

it('provides notification settings with explicit push installation controls', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test(NotificationSettings::class)
        ->assertSee(__('customer.notifications.settings_title'))
        ->assertSee(__('customer.notifications.install_push'))
        ->assertSee(__('customer.notifications.save'))
        ->assertDontSee('customer.notifications.save', false)
        ->assertSee('store-switch__track', false)
        ->assertSee('notification-push-install', false)
        ->assertSee('x-show="!subscribed && supported && configured"', false)
        ->assertSee('x-cloak', false)
        ->set('preferences.payment_recorded.push_enabled', false)
        ->call('savePreferences')
        ->assertSee(__('customer.notifications.saved'));

    expect($user->fresh()->notificationPreferences()->where('key', 'payment_recorded')->value('push_enabled'))->toBeFalse();
});

it('keeps customer notification switches enabled when no merchant override exists', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $html = Livewire::actingAs($user)
        ->test(NotificationSettings::class)
        ->html();

    expect($html)
        ->toContain('wire:model="preferences.payment_recorded.in_app_enabled"')
        ->not->toContain('wire:model="preferences.payment_recorded.in_app_enabled" disabled');
});

it('disables customer notification switches for an explicit merchant-managed event', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    NotificationTemplate::query()->create([
        'key' => 'payment_recorded',
        'subject' => 'Payment recorded',
        'body' => 'Payment recorded',
        'enabled' => true,
        'user_choice' => false,
    ]);

    Livewire::actingAs($user)
        ->test(NotificationSettings::class)
        ->assertSee('wire:model="preferences.payment_recorded.in_app_enabled" disabled', false);
});

it('keeps notification badges in the shared storefront chrome', function (): void {
    $css = File::get(base_path('themes/default/resources/css/components/_store.css'));

    expect($css)
        ->toContain('.store-header__account-count {')
        ->toContain('.store-account-menu__notification-count {')
        ->toContain('.store-drawer__count {')
        ->not->toContain('.store-drawer__notification {');
});

it('keeps notifications inside the mobile account panel with a notification icon', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $html = $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->getContent();

    $accountPanel = str($html)->between('<div id="store-mobile-account"', '</div>')->toString();

    expect($html)
        ->toContain('store-account-menu__item--notifications')
        ->toContain('store-drawer__link--notifications')
        ->toContain('store-drawer__notification-icon')
        ->toContain('M18 8a6 6 0 0 0-12 0')
        ->not->toContain('class="store-drawer__notification"');

    expect($accountPanel)->toContain('store-drawer__link--notifications');
});

it('keeps notification settings visible in the account sidebar', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('customer.notification-settings'));
    $accountNav = str($response->getContent())->between('<aside class="store-account__nav"', '</aside>')->toString();

    expect($accountNav)
        ->toContain('href="'.route('customer.notification-settings').'"')
        ->toContain('aria-current="page"')
        ->not->toContain('href="'.route('customer.notifications').'"');
});

it('stores a browser push subscription only for the authenticated owner', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $payload = [
        'endpoint' => 'https://push.example.test/subscription',
        'keys' => [
            'p256dh' => 'public-key',
            'auth' => 'auth-key',
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('customer.notifications.push-subscription'), $payload)
        ->assertOk()
        ->assertJson(['subscribed' => true]);

    expect(PushSubscription::query()->where('user_id', $user->id)->first())
        ->endpoint->toBe($payload['endpoint']);
});

it('serves the public push worker without exposing private vapid material', function (): void {
    $this->get('/sw.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertSee('push')
        ->assertDontSee('privateKey');
});

it('returns a safe push configuration response when vapid is not configured', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->getJson(route('customer.notifications.push-config'))
        ->assertOk()
        ->assertJson(['configured' => false])
        ->assertJsonMissingPath('privateKey');
});
