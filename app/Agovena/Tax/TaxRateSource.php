<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

enum TaxRateSource: string
{
    case Override = 'override';
    case Disabled = 'disabled';
    case Automatic = 'automatic';
    case Fallback = 'fallback';
    case None = 'none';
}
