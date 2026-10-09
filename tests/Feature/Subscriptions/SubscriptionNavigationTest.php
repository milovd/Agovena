<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Recurring\Http\Livewire\Admin\SubscriptionsIndex;
use App\Livewire\Admin\PlanChanges\Index as PlanChangesIndex;
use App\Models\AgovenaModule;
use App\Models\Product;
use App\Models\ProductPlanChange;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function subscriptionLinks(string $html, string $xpath): DOMNodeList
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return (new DOMXPath($document))->query($xpath);
}

it('keeps both Core recurring destinations when a retired optional subscription module is disabled', function (): void {
    AgovenaModule::query()->where('module_id', 'subscriptions')->delete();

    $items = AdminNavigation::filterVisible(
        collect(app(AdminRegistrar::class)->navigationItems()),
        app(ModuleManager::class),
    )->keyBy('id');

    expect($items->get('subscriptions')?->href)->toBe('/admin/subscriptions')
        ->and($items->get('plan-changes')?->href)->toBe('/admin/plan-changes')
        ->and($items->get('subscriptions')?->permission)->toBe('subscriptions.view')
        ->and($items->get('plan-changes')?->permission)->toBe('plan-changes.view');
});

it('keeps the active tab after Livewire update requests', function (): void {
    $staff = $this->createStaff(permissions: ['subscriptions.view', 'plan-changes.view']);

    $list = Livewire::actingAs($staff)->test(SubscriptionsIndex::class)->set('status', 'active');
    expect(subscriptionLinks($list->html(), '//nav[@aria-label="'.__('subscriptions::admin.tabs_label').'"]//a[@href="'.route('admin.subscriptions.index').'" and @aria-current="page"]'))->toHaveCount(1);

    $changes = Livewire::actingAs($staff)->test(PlanChangesIndex::class);
    expect(subscriptionLinks($changes->html(), '//nav[@aria-label="'.__('subscriptions::admin.tabs_label').'"]//a[@href="'.route('admin.plan-changes.index').'" and @aria-current="page"]'))->toHaveCount(1);
});

it('preserves independent permissions, one sidebar destination and exact current-page semantics', function (array $permissions, int $subscriptionsStatus, int $planChangesStatus, string $sidebarHref, int $tabCount): void {
    $staff = $this->createStaff(permissions: $permissions);
    $this->actingAs($staff);

    $subscriptions = $this->get(route('admin.subscriptions.index'));
    $changes = $this->get(route('admin.plan-changes.index'));
    $subscriptions->assertStatus($subscriptionsStatus);
    $changes->assertStatus($planChangesStatus);

    $page = $subscriptionsStatus === 200 ? $subscriptions : $changes;
    $html = $page->getContent();
    $sidebarXpath = '//nav[contains(concat(" ", normalize-space(@class), " "), " admin-nav ")]//a[@href="/admin/subscriptions" or @href="/admin/plan-changes"]';
    $sidebar = subscriptionLinks($html, $sidebarXpath);
    expect($sidebar)->toHaveCount(1)
        ->and($sidebar->item(0)->attributes->getNamedItem('href')->nodeValue)->toBe($sidebarHref)
        ->and(subscriptionLinks($html, '//div[contains(@class, "admin-nav__section") and contains(@data-nav-key, "nav-groupssales")]//a[@href="'.$sidebarHref.'"]'))->toHaveCount(1);

    $tabs = subscriptionLinks($html, '//nav[@aria-label="'.__('subscriptions::admin.tabs_label').'"]//a');
    expect($tabs)->toHaveCount($tabCount);
    foreach ($tabs as $tab) {
        expect($tab->attributes->getNamedItem('href')->nodeValue)->toBeIn([
            route('admin.subscriptions.index'), route('admin.plan-changes.index'),
        ]);
    }

    if ($subscriptionsStatus === 200 && $planChangesStatus === 200) {
        expect(subscriptionLinks($subscriptions->getContent(), $sidebarXpath.'[@href="/admin/subscriptions" and @aria-current="page"]'))->toHaveCount(1)
            ->and(subscriptionLinks($changes->getContent(), $sidebarXpath.'[@href="/admin/subscriptions" and contains(@class, "admin-nav__link--active") and not(@aria-current)]'))->toHaveCount(1)
            ->and(subscriptionLinks($changes->getContent(), '//nav[@aria-label="'.__('subscriptions::admin.tabs_label').'"]//a[@href="'.route('admin.plan-changes.index').'" and @aria-current="page"]'))->toHaveCount(1);
    } else {
        expect(subscriptionLinks($html, $sidebarXpath.'[@aria-current="page"]'))->toHaveCount(1)
            ->and(subscriptionLinks($html, '//nav[@aria-label="'.__('subscriptions::admin.tabs_label').'"]//a[@aria-current="page"]'))->toHaveCount(1);
    }
})->with([
    'subscriptions only' => [['subscriptions.view'], 200, 403, '/admin/subscriptions', 1],
    'plan changes only' => [['plan-changes.view'], 403, 200, '/admin/plan-changes', 1],
    'both' => [['subscriptions.view', 'plan-changes.view'], 200, 200, '/admin/subscriptions', 2],
]);

it('hides both destinations from staff lacking either permission', function (): void {
    $staff = $this->createStaff(permissions: ['dashboard.view']);
    $html = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();

    expect(subscriptionLinks($html, '//nav[contains(@class, "admin-nav")]//a[@href="/admin/subscriptions" or @href="/admin/plan-changes"]'))->toHaveCount(0);
    $this->get(route('admin.subscriptions.index'))->assertForbidden();
    $this->get(route('admin.plan-changes.index'))->assertForbidden();
});

it('retains plan-change management for the plan-only staff route', function (): void {
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    $staff = $this->createStaff(permissions: ['plan-changes.view', 'plan-changes.manage']);

    Livewire::actingAs($staff)->test(PlanChangesIndex::class)
        ->assertSee('wire:submit="save"', false)
        ->set('from_product_id', $from->id)
        ->set('to_product_id', $to->id)
        ->call('save')
        ->assertHasNoErrors();

    $change = ProductPlanChange::query()->where('from_product_id', $from->id)->where('to_product_id', $to->id)->firstOrFail();
    Livewire::actingAs($staff)->test(PlanChangesIndex::class)->call('delete', $change->id)->assertHasNoErrors();
    expect(ProductPlanChange::query()->find($change->id))->toBeNull();
});

it('keeps plan-change management hidden and forbidden without manage permission', function (): void {
    $staff = $this->createStaff(permissions: ['plan-changes.view']);
    Livewire::actingAs($staff)->test(PlanChangesIndex::class)
        ->assertDontSee('wire:submit="save"', false)
        ->call('delete', 1)
        ->assertForbidden();
});

it('labels subscription sections in English and Dutch', function (): void {
    expect(__('subscriptions::admin.tabs_label', [], 'en'))->toBe('Subscription sections')
        ->and(__('subscriptions::admin.tabs_label', [], 'nl'))->toBe('Abonnementsonderdelen');
});
