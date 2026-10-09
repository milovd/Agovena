<?php

declare(strict_types=1);

use App\Livewire\Admin\Customers\Index;
use App\Livewire\Admin\Customers\Properties;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function customerNavigationLinks(string $html, string $xpath): DOMNodeList
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return (new DOMXPath($document))->query($xpath);
}

it('keeps the correct customer tab active after Livewire requests', function (): void {
    $staff = $this->createStaff(permissions: ['customers.view', 'customers.manage']);

    $list = Livewire::actingAs($staff)->test(Index::class)
        ->set('search', 'missing-customer');
    expect(customerNavigationLinks($list->html(), '//nav[@aria-label="'.__('admin.customers.tabs_label').'"]//a[@href="'.route('admin.customers.index').'" and @aria-current="page"]'))->toHaveCount(1);

    $properties = Livewire::actingAs($staff)->test(Properties::class)
        ->call('create');
    expect(customerNavigationLinks($properties->html(), '//nav[@aria-label="'.__('admin.customers.tabs_label').'"]//a[@href="'.route('admin.customers.properties').'" and @aria-current="page"]'))->toHaveCount(1);
});

it('keeps customer navigation reachable with each independent permission', function (array $permissions, int $listStatus, int $propertiesStatus, string $sidebarHref, int $tabCount): void {
    $staff = $this->createStaff(permissions: $permissions);
    $this->actingAs($staff);

    $list = $this->get(route('admin.customers.index'));
    $properties = $this->get(route('admin.customers.properties'));
    $list->assertStatus($listStatus);
    $properties->assertStatus($propertiesStatus);

    $page = $listStatus === 200 ? $list : $properties;
    $html = $page->getContent();
    $sidebar = customerNavigationLinks($html, '//nav[contains(concat(" ", normalize-space(@class), " "), " admin-nav ")]//a[starts-with(@href, "/admin/customers")]');
    expect($sidebar)->toHaveCount(1)
        ->and($sidebar->item(0)->attributes->getNamedItem('href')->nodeValue)->toBe($sidebarHref)
        ->and(customerNavigationLinks($html, '//div[contains(@class, "admin-nav__section") and contains(@data-nav-key, "nav-groupscustomers")]//a[@href="'.$sidebarHref.'"]'))->toHaveCount(1);

    $tabs = customerNavigationLinks($html, '//nav[@aria-label="'.__('admin.customers.tabs_label').'"]//a');
    expect($tabs)->toHaveCount($tabCount);
    foreach ($tabs as $tab) {
        expect($tab->attributes->getNamedItem('href')->nodeValue)->toBeIn([
            route('admin.customers.index'), route('admin.customers.properties'),
        ]);
        if ($tabCount === 1) {
            expect($tab->attributes->getNamedItem('href')->nodeValue)->toBe(url($sidebarHref));
        }
    }

    if ($listStatus === 200 && $propertiesStatus === 200) {
        expect(customerNavigationLinks($list->getContent(), '//nav[contains(@class, "admin-nav")]//a[@href="/admin/customers" and @aria-current="page"]'))->toHaveCount(1)
            ->and(customerNavigationLinks($properties->getContent(), '//nav[contains(@class, "admin-nav")]//a[@href="/admin/customers" and contains(@class, "admin-nav__link--active") and not(@aria-current)]'))->toHaveCount(1)
            ->and(customerNavigationLinks($properties->getContent(), '//nav[@aria-label="'.__('admin.customers.tabs_label').'"]//a[@href="'.route('admin.customers.properties').'" and @aria-current="page"]'))->toHaveCount(1);
    } else {
        expect(customerNavigationLinks($html, '//nav[contains(@class, "admin-nav")]//a[@aria-current="page" and starts-with(@href, "/admin/customers")]'))->toHaveCount(1)
            ->and(customerNavigationLinks($html, '//nav[@aria-label="'.__('admin.customers.tabs_label').'"]//a[@aria-current="page"]'))->toHaveCount(1);
    }
})->with([
    'list only' => [['customers.view'], 200, 403, '/admin/customers', 1],
    'properties only' => [['customers.manage'], 403, 200, '/admin/customers/properties', 1],
    'both' => [['customers.view', 'customers.manage'], 200, 200, '/admin/customers', 2],
]);

it('does not expose either customer destination without permission', function (): void {
    $staff = $this->createStaff(permissions: ['dashboard.view']);
    $html = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();

    expect(customerNavigationLinks($html, '//nav[contains(@class, "admin-nav")]//a[starts-with(@href, "/admin/customers")]'))->toHaveCount(0);
    $this->get(route('admin.customers.index'))->assertForbidden();
    $this->get(route('admin.customers.properties'))->assertForbidden();
});

it('labels customer sections in both locales', function (): void {
    expect(__('admin.customers.tabs_label', [], 'en'))->toBe('Customer sections')
        ->and(__('admin.customers.tabs_label', [], 'nl'))->toBe('Klantonderdelen');
});
