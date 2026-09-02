<?php

declare(strict_types=1);

use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/__theme-errors/forbidden', static fn () => abort(403));
    Route::get('/__theme-errors/server', static fn () => abort(500));
    Route::get('/__theme-errors/status/{status}', static fn (int $status) => abort($status))
        ->whereNumber('status');
});

test('http error pages render from theme-owned views', function (int $status, string $url) {
    $this->get($url)
        ->assertStatus($status)
        ->assertSee('store-chrome', false)
        ->assertSee('store-footer', false)
        ->assertSee('data-cookie-open', false)
        ->assertSee('data-cookie-panel', false)
        ->assertDontSee('data-cookie-banner', false)
        ->assertSee('store-error', false)
        ->assertSee('data-error-status="'.$status.'"', false)
        ->assertDontSee(__('errors.status_label', ['status' => $status]), false)
        ->assertDontSee('install-panel', false)
        ->assertDontSee('c-error-page', false)
        ->assertDontSee('Couldn\'t find this page', false);
})->with([
    'not found' => [404, '/__theme-errors/missing'],
    'forbidden' => [403, '/__theme-errors/forbidden'],
    'server error' => [500, '/__theme-errors/server'],
    'session expired' => [419, '/__theme-errors/status/419'],
    'rate limited' => [429, '/__theme-errors/status/429'],
    'method not allowed' => [405, '/__theme-errors/status/405'],
    'http version unsupported' => [505, '/__theme-errors/status/505'],
    'service unavailable' => [503, '/__theme-errors/status/503'],
]);

test('error page puts the action below the illustration and keeps it viewport-safe', function () {
    $html = $this->get('/__theme-errors/missing')->getContent();
    $artPosition = strpos($html, 'class="store-error__art"');
    $ledePosition = strpos($html, 'class="store-error__lede"');
    $descriptionPosition = strpos($html, 'class="store-error__description"');
    $actionsPosition = strpos($html, 'class="store-error__actions"');
    $css = file_get_contents(base_path('themes/default/resources/css/components/_error-page.css'));

    expect($artPosition)->toBeInt()
        ->and($ledePosition)->toBeInt()
        ->and($descriptionPosition)->toBeInt()
        ->and($actionsPosition)->toBeInt()
        ->and($artPosition)->toBeLessThan($descriptionPosition)
        ->and($descriptionPosition)->toBeLessThan($ledePosition)
        ->and($descriptionPosition)->toBeLessThan($actionsPosition)
        ->and($ledePosition)->toBeLessThan($actionsPosition)
        ->and($css)->toContain('100svh')
        ->and($css)->toContain('aspect-ratio: 620 / 320')
        ->and($css)->toContain('container-type: inline-size')
        ->and($css)->toContain('width: 100%')
        ->and($css)->toContain('height: 100%')
        ->and($css)->toContain('width: min(100%, 26rem)')
        ->and($css)->toContain('.store-error[data-error-status="404"] .store-error__title')
        ->and($css)->toContain('margin-top: -1.5rem');
});

test('theme error layout exposes persisted color mode support', function () {
    $this->get('/__theme-errors/missing')
        ->assertStatus(404)
        ->assertSee("localStorage.getItem('agovena.theme')", false)
        ->assertSee('[data-theme="dark"]', false)
        ->assertSee('store-header__theme-toggle', false)
        ->assertSee(__('errors.theme_to_dark'), false)
        ->assertSee(__('errors.theme_to_light'), false);
});

test('default theme owns the supported http error pages', function () {
    $theme = app(ThemeManager::class)->active();

    foreach ([404, 403, 419, 500, 503, 405, 505, 429] as $status) {
        expect($theme->errorView($status))->toBe('errors.'.$status)
            ->and(view()->exists($theme->errorView($status)))->toBeTrue();
    }
});
