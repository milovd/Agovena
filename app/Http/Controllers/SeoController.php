<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class SeoController
{
    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = [route('storefront.home'), route('storefront.categories')];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</loc></url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
