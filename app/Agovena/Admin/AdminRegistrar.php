<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

interface AdminRegistrar
{
    public function navigation(NavigationItem $item): void;

    public function page(PageDefinition $page): void;

    public function permission(string $ability, string $label): void;

    public function widget(DashboardWidget $widget): void;

    public function settingsGroup(SettingsGroup $group): void;

    public function settingsField(SettingsField $field): void;

    public function orderDetailSection(OrderDetailSection $section): void;

    public function customerDetailSection(CustomerDetailSection $section): void;

    public function productTab(ProductTab $tab): void;

    /** @return list<NavigationItem> */
    public function navigationItems(): array;

    /** @return list<PageDefinition> */
    public function pages(): array;

    /** @return array<string, string> */
    public function permissions(): array;

    /** @return list<DashboardWidget> */
    public function widgets(): array;

    /** @return list<SettingsGroup> */
    public function settingsGroups(): array;

    public function settingsGroupById(string $id): ?SettingsGroup;

    /** @return list<SettingsField> */
    public function settingsFieldsFor(string $group): array;

    /** @return list<OrderDetailSection> */
    public function orderDetailSections(): array;

    /** @return list<CustomerDetailSection> */
    public function customerDetailSections(): array;

    /** @return list<ProductTab> */
    public function productTabs(): array;
}
