<?php

declare(strict_types=1);

use App\Livewire\Admin\Invoices\Design;
use App\Livewire\Admin\Invoices\Index;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function invoiceNavigationLinks(string $html, string $xpath): DOMNodeList
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return (new DOMXPath($document))->query($xpath);
}

it('keeps the correct tab active after Livewire requests', function (): void {
    $staff = $this->createStaff(permissions: ['invoices.view', 'invoices.design']);

    $list = Livewire::actingAs($staff)->test(Index::class)
        ->set('search', 'missing-invoice');
    expect(invoiceNavigationLinks($list->html(), '//nav[@aria-label="'.__('admin.invoice_design.tabs_label').'"]//a[@href="'.route('admin.invoices.index').'" and @aria-current="page"]'))->toHaveCount(1);

    $design = Livewire::actingAs($staff)->test(Design::class)
        ->call('selectTemplate', 'banner');
    expect(invoiceNavigationLinks($design->html(), '//nav[@aria-label="'.__('admin.invoice_design.tabs_label').'"]//a[@href="'.route('admin.invoices.design').'" and @aria-current="page"]'))->toHaveCount(1);
});

it('keeps invoice navigation reachable with each independent permission', function (array $permissions, int $listStatus, int $designStatus, string $sidebarHref, int $tabCount): void {
    $staff = $this->createStaff(permissions: $permissions);
    $this->actingAs($staff);

    $list = $this->get(route('admin.invoices.index'));
    $design = $this->get(route('admin.invoices.design'));
    $list->assertStatus($listStatus);
    $design->assertStatus($designStatus);

    $page = $listStatus === 200 ? $list : $design;
    $html = $page->getContent();
    $sidebar = invoiceNavigationLinks($html, '//nav[contains(concat(" ", normalize-space(@class), " "), " admin-nav ")]//a[starts-with(@href, "/admin/invoices")]');
    expect($sidebar)->toHaveCount(1)
        ->and($sidebar->item(0)->attributes->getNamedItem('href')->nodeValue)->toBe($sidebarHref)
        ->and(invoiceNavigationLinks($html, '//div[contains(@class, "admin-nav__section") and contains(@data-nav-key, "nav-groupssales")]//a[@href="'.$sidebarHref.'"]'))->toHaveCount(1);

    $tabs = invoiceNavigationLinks($html, '//nav[@aria-label="'.__('admin.invoice_design.tabs_label').'"]//a');
    expect($tabs)->toHaveCount($tabCount);
    foreach ($tabs as $tab) {
        expect($tab->attributes->getNamedItem('href')->nodeValue)->toBeIn([
            route('admin.invoices.index'), route('admin.invoices.design'),
        ]);
        if ($tabCount === 1) {
            expect($tab->attributes->getNamedItem('href')->nodeValue)->toBe(url($sidebarHref));
        }
    }

    if ($listStatus === 200 && $designStatus === 200) {
        expect(invoiceNavigationLinks($list->getContent(), '//nav[contains(@class, "admin-nav")]//a[@href="/admin/invoices" and @aria-current="page"]'))->toHaveCount(1)
            ->and(invoiceNavigationLinks($design->getContent(), '//nav[contains(@class, "admin-nav")]//a[@href="/admin/invoices" and contains(@class, "admin-nav__link--active") and not(@aria-current)]'))->toHaveCount(1)
            ->and(invoiceNavigationLinks($design->getContent(), '//nav[@aria-label="'.__('admin.invoice_design.tabs_label').'"]//a[@href="'.route('admin.invoices.design').'" and @aria-current="page"]'))->toHaveCount(1);
    } else {
        expect(invoiceNavigationLinks($html, '//nav[contains(@class, "admin-nav")]//a[@aria-current="page" and starts-with(@href, "/admin/invoices")]'))->toHaveCount(1)
            ->and(invoiceNavigationLinks($html, '//nav[@aria-label="'.__('admin.invoice_design.tabs_label').'"]//a[@aria-current="page"]'))->toHaveCount($tabCount);
    }
})->with([
    'list only' => [['invoices.view'], 200, 403, '/admin/invoices', 1],
    'design only' => [['invoices.design'], 403, 200, '/admin/invoices/design', 1],
    'both' => [['invoices.view', 'invoices.design'], 200, 200, '/admin/invoices', 2],
]);
