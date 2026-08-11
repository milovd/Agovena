<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

final class ThemeSettingsSchema
{
    /** @param  list<ThemeSettingField>  $fields */
    public function __construct(public readonly array $fields) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->fields as $field) {
            $defaults[$field->key] = $field->default;
        }

        return $defaults;
    }

    public function field(string $key): ?ThemeSettingField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<ThemeSettingField>>
     */
    public function grouped(): array
    {
        $groups = [];
        $sorted = $this->fields;
        usort($sorted, fn (ThemeSettingField $a, ThemeSettingField $b): int => $a->sort <=> $b->sort);

        foreach ($sorted as $field) {
            $groups[$field->group][] = $field;
        }

        return $groups;
    }
}
