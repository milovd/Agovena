<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerPropertyType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Select = 'select';
    case Country = 'country';
    case Checkbox = 'checkbox';
    case Date = 'date';

    public function hasChoices(): bool
    {
        return $this === self::Select;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
