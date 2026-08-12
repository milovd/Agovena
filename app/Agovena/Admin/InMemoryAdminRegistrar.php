<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final class InMemoryAdminRegistrar implements AdminRegistrar
{
    /** @var list<NavigationItem> */
    private array $navigation = [];

    /** @var list<PageDefinition> */
    private array $pages = [];

    /** @var array<string, string> */
    private array $permissions = [];

    /** @var list<DashboardWidget> */
    private array $widgets = [];

    /** @var array<string, SettingsGroup> */
    private array $settingsGroups = [];

    /** @var list<SettingsField> */
    private array $settingsFields = [];

    /** @var list<OrderDetailSection> */
    private array $orderDetailSections = [];

    public function navigation(NavigationItem $item): void
    {
        $this->navigation[] = $item;
    }

    public function page(PageDefinition $page): void
    {
        $this->pages[] = $page;
    }

    public function permission(string $ability, string $label): void
    {
        $this->permissions[$ability] = $label;
    }

    public function widget(DashboardWidget $widget): void
    {
        $this->widgets[] = $widget;
    }

    public function settingsGroup(SettingsGroup $group): void
    {
        $this->settingsGroups[$group->id] = $group;
    }

    public function settingsField(SettingsField $field): void
    {
        $this->settingsFields[] = $field;
    }

    public function orderDetailSection(OrderDetailSection $section): void
    {
        $this->orderDetailSections[] = $section;
    }

    /** @return list<NavigationItem> */
    public function navigationItems(): array
    {
        $items = $this->navigation;
        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->sort <=> $b->sort);

        return $items;
    }

    /** @return list<PageDefinition> */
    public function pages(): array
    {
        return $this->pages;
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /** @return list<DashboardWidget> */
    public function widgets(): array
    {
        $widgets = $this->widgets;
        usort($widgets, static fn (DashboardWidget $a, DashboardWidget $b): int => $a->sort <=> $b->sort);

        return $widgets;
    }

    /** @return list<SettingsGroup> */
    public function settingsGroups(): array
    {
        $groups = array_values($this->settingsGroups);
        usort($groups, static fn (SettingsGroup $a, SettingsGroup $b): int => $a->sort <=> $b->sort);

        return $groups;
    }

    public function settingsGroupById(string $id): ?SettingsGroup
    {
        return $this->settingsGroups[$id] ?? null;
    }

    /** @return list<SettingsField> */
    public function settingsFieldsFor(string $group): array
    {
        $fields = array_values(array_filter(
            $this->settingsFields,
            static fn (SettingsField $field): bool => $field->group === $group,
        ));
        usort($fields, static fn (SettingsField $a, SettingsField $b): int => $a->sort <=> $b->sort);

        return $fields;
    }

    /** @return list<OrderDetailSection> */
    public function orderDetailSections(): array
    {
        $sections = $this->orderDetailSections;
        usort($sections, static fn (OrderDetailSection $a, OrderDetailSection $b): int => $a->sort <=> $b->sort);

        return $sections;
    }
}
