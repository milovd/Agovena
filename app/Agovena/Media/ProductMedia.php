<?php

declare(strict_types=1);

namespace App\Agovena\Media;

use App\Models\Product;

/**
 * Storefront-authoritative product image. Never returns a URL that 404s.
 */
final class ProductMedia
{
    public static function primaryUrl(?Product $product): ?string
    {
        if ($product === null) {
            return null;
        }

        $galleryPath = $product->relationLoaded('images')
            ? $product->images->first()?->path
            : $product->images()->orderBy('sort')->value('path');

        return PublicMedia::url(is_string($galleryPath) && $galleryPath !== '' ? $galleryPath : $product->image_path);
    }
}
