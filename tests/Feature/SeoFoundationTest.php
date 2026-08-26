<?php

declare(strict_types=1);

it('serves robots and sitemap responses with public-only routes', function (): void {
    $robots = $this->get('/robots.txt');
    $sitemap = $this->get('/sitemap.xml');

    $robots->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /account')
        ->assertSee('/sitemap.xml');
    $sitemap->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<urlset', false)
        ->assertSee(route('storefront.home'), false)
        ->assertSee(route('storefront.categories'), false);
});
