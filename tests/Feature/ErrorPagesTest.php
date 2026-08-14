<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('missing storefront pages use the branded 404', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee(__('errors.404.heading'), false)
        ->assertDontSee('SQLSTATE', false);
});

test('customers cannot open Admin and see a branded 403', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer->user)
        ->get(route('admin.dashboard'))
        ->assertForbidden()
        ->assertSee(__('errors.403.heading'), false);
});

test('production mode hides exception details behind a branded 500', function () {
    config(['app.debug' => false]);

    Route::middleware('web')->get('/__forced-error', function (): never {
        throw new RuntimeException('secret diagnostic detail');
    });

    $this->get('/__forced-error')
        ->assertStatus(500)
        ->assertSee(__('errors.500.heading'), false)
        ->assertDontSee('secret diagnostic detail', false)
        ->assertDontSee('RuntimeException', false);
});

test('expired sessions use a branded 419 page', function () {
    config(['app.debug' => false]);

    Route::middleware('web')->get('/__forced-419', fn () => abort(419));

    $this->get('/__forced-419')
        ->assertStatus(419)
        ->assertSee(__('errors.419.heading'), false);
});

test('rate limits and maintenance use branded pages', function () {
    config(['app.debug' => false]);

    Route::middleware('web')->get('/__forced-429', fn () => abort(429));
    Route::middleware('web')->get('/__forced-503', fn () => abort(503));

    $this->get('/__forced-429')
        ->assertStatus(429)
        ->assertSee(__('errors.429.heading'), false);

    $this->get('/__forced-503')
        ->assertStatus(503)
        ->assertSee(__('errors.503.heading'), false)
        ->assertDontSee('SQLSTATE', false);
});

test('doctor fails when debug is enabled in production', function () {
    config([
        'app.env' => 'production',
        'app.debug' => true,
    ]);

    $this->artisan('agovena:doctor')->assertFailed();
});
