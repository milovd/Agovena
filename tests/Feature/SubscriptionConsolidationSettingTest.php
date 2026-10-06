<?php

use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Settings\Hub;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('store settings expose the subscription consolidation window with its default', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'store')
        ->assertSee(__('admin.settings.fields.subscription_consolidation_window_days'))
        ->assertSet('values.subscription_consolidation_window_days', 31);
});

test('admin can save a valid subscription consolidation window', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'store')
        ->set('values.subscription_consolidation_window_days', 14)
        ->call('save')
        ->assertHasNoErrors();

    expect((int) app(SettingsRepository::class)->get('store', 'subscription_consolidation_window_days'))->toBe(14);
});

test('subscription consolidation window rejects values outside 1 to 31 days', function (int $days) {
    $staff = $this->createStaff();
    $settings = app(SettingsRepository::class);
    $settings->set('store', 'subscription_consolidation_window_days', 20);

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'store')
        ->set('values.subscription_consolidation_window_days', $days)
        ->call('save')
        ->assertHasErrors(['values.subscription_consolidation_window_days']);

    expect((int) $settings->get('store', 'subscription_consolidation_window_days'))->toBe(20);
})->with([0, 32]);
