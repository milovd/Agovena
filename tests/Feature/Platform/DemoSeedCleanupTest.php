<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('removes legacy demo catalog and menu records during a forced reseed', function (): void {
    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output());

    $legacyCategory = Category::factory()->create([
        'name' => 'Apparel',
        'slug' => 'apparel',
    ]);
    Product::factory()->create([
        'name' => 'Linen Overshirt',
        'slug' => 'linen-overshirt',
        'category_id' => $legacyCategory->id,
    ]);
    $legacyPageId = DB::table('pages')->insertGetId([
        'title' => 'Terms',
        'slug' => 'terms',
        'body' => 'Demo terms page. Publish your own legal copy before going live.',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $legalMenuId = DB::table('menus')->where('handle', 'footer_legal')->value('id');
    DB::table('menu_items')->insert([
        'menu_id' => $legalMenuId,
        'label' => 'Terms',
        'type' => 'page',
        'page_id' => $legacyPageId,
        'sort' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output())
        ->and(Product::query()->where('slug', 'linen-overshirt')->exists())->toBeFalse()
        ->and(Category::query()->where('slug', 'apparel')->exists())->toBeFalse()
        ->and(DB::table('pages')->where('slug', 'terms')->exists())->toBeFalse()
        ->and(DB::table('menu_items')->where('label', 'Terms')->exists())->toBeFalse()
        ->and(DB::table('menu_items')->where('label', 'Demo Terms')->exists())->toBeTrue();
});

it('seeds orders without storing demo metadata as customer properties', function (): void {
    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output());

    $orders = Order::query()->where('number', 'like', 'DEMO-%')->get();

    expect($orders)->not->toBeEmpty();
    foreach ($orders as $order) {
        expect($order->custom_properties_snapshot)->toBe([]);
    }
});

it('removes legacy orphaned product option choices during a forced reseed', function (): void {
    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output());

    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }

    Schema::withoutForeignKeyConstraints(function (): void {
        DB::table('product_option_choices')->insert([
            'product_option_id' => 999999,
            'value' => 'legacy-choice',
            'label' => 'Legacy choice',
            'price_adjustment_amount' => 0,
            'sort' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    expect(DB::table('product_option_choices')->where('product_option_id', 999999)->exists())->toBeTrue();

    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output())
        ->and(DB::table('product_option_choices')->whereNotExists(function ($query): void {
            $query->select(DB::raw(1))
                ->from('product_options')
                ->whereColumn('product_options.id', 'product_option_choices.product_option_id');
        })->count())->toBe(0);
});
