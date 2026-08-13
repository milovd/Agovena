<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductOptionType: string
{
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Toggle = 'toggle';
    case Text = 'text';
    case Number = 'number';

    public function hasChoices(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }

    public function isBoolean(): bool
    {
        return $this === self::Toggle;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
