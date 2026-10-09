<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

final class PageImageSourceSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): array
    {
        return ['img'];
    }

    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return preg_match('~\A/storage/pages/[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp|gif)\z~i', $value) === 1
            ? $value
            : null;
    }
}
