<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Json = 'json';
    case Xml = 'xml';

    public function extension(): string
    {
        return $this->value;
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Json => 'application/json; charset=UTF-8',
            self::Xml => 'application/xml; charset=UTF-8',
        };
    }
}
