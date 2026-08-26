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

it('renders safe SEO metadata for public storefront pages and noindex for private surfaces', function (): void {
    $public = $this->get('/');
    $private = $this->get('/login');

    $public->assertOk()
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.route('storefront.home').'">', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('application/ld+json', false)
        ->assertDontSee('noindex', false);
    $private->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});
