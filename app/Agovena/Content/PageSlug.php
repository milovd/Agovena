<?php

declare(strict_types=1);

namespace App\Agovena\Content;

final class PageSlug
{
    public const ROUTE_PATTERN = '^(?!(?:admin|install|cart|checkout|products|categories|orders|account|login|register)\z)[A-Za-z0-9-]+\z';

    public const VALIDATION_PATTERN = '/'.self::ROUTE_PATTERN.'/';
}
