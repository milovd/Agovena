<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AgovenaSeedDemoCommand extends Command
{
    protected $signature = 'agovena:seed-demo {--force : Replace existing demo catalog content}';

    protected $description = 'Seed local development demo catalog (refuses in production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to seed demo data in production.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            ProductImage::query()->delete();
            Product::query()->delete();
            Category::query()->delete();
            MenuItem::query()->delete();
            Menu::query()->delete();
            Page::query()->delete();
        } elseif (Product::query()->exists()) {
            $this->warn('Catalog already has products. Re-run with --force to replace demo content.');

            return self::SUCCESS;
        }

        $this->info('Downloading demo product photos (Unsplash, local cache)…');

        $phones = Category::query()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'description' => 'Smartphones for everyday use.',
            'image_path' => $this->storePhoto('category-phones', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&h=700&q=80'),
            'is_active' => true,
        ]);
        $audio = Category::query()->create([
            'name' => 'Audio',
            'slug' => 'audio',
            'description' => 'Headphones and earbuds.',
            'image_path' => $this->storePhoto('category-audio', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&h=700&q=80'),
            'is_active' => true,
        ]);
        $accessories = Category::query()->create([
            'name' => 'Accessories',
            'slug' => 'accessories',
            'description' => 'Cases, chargers, and everyday extras.',
            'image_path' => $this->storePhoto('category-accessories', 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=900&h=700&q=80'),
            'is_active' => true,
        ]);

        $android = Category::query()->create([
            'parent_id' => $phones->id,
            'name' => 'Android',
            'slug' => 'android',
            'description' => 'Android smartphones.',
            'image_path' => $this->storePhoto('category-android', 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?auto=format&fit=crop&w=900&h=700&q=80'),
            'is_active' => true,
        ]);
        $iphone = Category::query()->create([
            'parent_id' => $phones->id,
            'name' => 'iPhone',
            'slug' => 'iphone',
            'description' => 'iPhone models.',
            'image_path' => $this->storePhoto('category-iphone', 'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=900&h=700&q=80'),
            'is_active' => true,
        ]);

        $this->storePhoto('hero-promo', 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=1200&h=900&q=80');
        $this->storePhoto('promo-split', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1200&h=900&q=80');

        $products = [
            ['name' => 'Nova Phone 14', 'subtitle' => '6.1 inch OLED, dual camera, all-day battery.', 'category' => $android, 'price' => 69900, 'desc' => "6.1\" OLED display, dual camera, and all-day battery.\n\nNova Phone 14 is built for everyday clarity: bright screen, fast charging, and a clean software experience without the noise.", 'photos' => [
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1580910051074-3eb694886505?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Smartphone'],
                ['label' => 'Display', 'value' => '6.1 inch OLED'],
                ['label' => 'Storage', 'value' => '128 GB'],
                ['label' => 'Battery', 'value' => 'All-day'],
                ['label' => 'Connectivity', 'value' => '5G, Wi‑Fi 6, Bluetooth 5.3'],
            ]],
            ['name' => 'Nova Phone 14 Pro', 'category' => $android, 'price' => 89900, 'desc' => 'Pro camera system with bright AMOLED panel.', 'photos' => [
                'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Smartphone'],
                ['label' => 'Display', 'value' => '6.5" AMOLED'],
                ['label' => 'Camera', 'value' => 'Pro triple system'],
                ['label' => 'Storage', 'value' => '256 GB'],
            ]],
            ['name' => 'Pulse X', 'category' => $android, 'price' => 54900, 'desc' => 'Compact Android phone with fast charging.', 'photos' => [
                'https://images.unsplash.com/photo-1580910051074-3eb694886505?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Smartphone'],
                ['label' => 'Charging', 'value' => 'Fast charge'],
                ['label' => 'Form factor', 'value' => 'Compact'],
            ]],
            ['name' => 'iPhone 15', 'category' => $iphone, 'price' => 92900, 'desc' => 'A16 performance in a slim aluminum design.', 'photos' => [
                'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Smartphone'],
                ['label' => 'Chip', 'value' => 'A16'],
                ['label' => 'Material', 'value' => 'Aluminum'],
            ]],
            ['name' => 'iPhone 15 Pro', 'category' => $iphone, 'price' => 119900, 'desc' => 'Titanium frame and advanced camera controls.', 'photos' => [
                'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Smartphone'],
                ['label' => 'Frame', 'value' => 'Titanium'],
                ['label' => 'Camera', 'value' => 'Pro controls'],
            ]],
            ['name' => 'Air Soft Buds', 'category' => $audio, 'price' => 12900, 'desc' => 'Lightweight earbuds with clear everyday sound.', 'photos' => [
                'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Earbuds'],
                ['label' => 'Fit', 'value' => 'In-ear'],
                ['label' => 'Use case', 'value' => 'Everyday / calls'],
            ]],
            ['name' => 'Studio Max Headphones', 'category' => $audio, 'price' => 34900, 'desc' => 'Over-ear headphones with balanced sound.', 'photos' => [
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?auto=format&fit=crop&w=800&h=800&q=80',
                'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Over-ear headphones'],
                ['label' => 'Sound', 'value' => 'Balanced'],
                ['label' => 'Use case', 'value' => 'Studio / commuting'],
            ]],
            ['name' => 'Clip Buds Mini', 'category' => $audio, 'price' => 7900, 'desc' => 'Compact buds for calls and commuting.', 'photos' => [
                'https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Earbuds'],
                ['label' => 'Form factor', 'value' => 'Compact'],
            ]],
            ['name' => 'Clear Case MagSafe', 'category' => $accessories, 'price' => 2900, 'desc' => 'Protective clear case with MagSafe ring.', 'photos' => [
                'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Phone case'],
                ['label' => 'Material', 'value' => 'Clear polycarbonate'],
                ['label' => 'Compatibility', 'value' => 'MagSafe'],
            ]],
            ['name' => '40W GaN Charger', 'category' => $accessories, 'price' => 3900, 'desc' => 'Compact dual-port USB-C charger.', 'photos' => [
                'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Charger'],
                ['label' => 'Power', 'value' => '40W GaN'],
                ['label' => 'Ports', 'value' => 'Dual USB-C'],
            ]],
            ['name' => 'Braided USB-C Cable', 'category' => $accessories, 'price' => 1900, 'desc' => '2m braided cable for phones and earbuds.', 'photos' => [
                'https://images.unsplash.com/photo-1625948515291-69613efd103f?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Cable'],
                ['label' => 'Length', 'value' => '2 m'],
                ['label' => 'Connector', 'value' => 'USB-C'],
            ]],
            ['name' => 'Desk Stand Aluminum', 'category' => $accessories, 'price' => 4500, 'desc' => 'Angled aluminum stand for phones.', 'photos' => [
                'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=800&h=800&q=80',
            ], 'specs' => [
                ['label' => 'Type', 'value' => 'Stand'],
                ['label' => 'Material', 'value' => 'Aluminum'],
                ['label' => 'Angle', 'value' => 'Desk viewing'],
            ]],
        ];

        foreach ($products as $row) {
            $slug = Str::slug($row['name']);
            $photos = $row['photos'];
            $primaryPath = $this->storePhoto($slug, $photos[0]);

            $product = Product::query()->create([
                'name' => $row['name'],
                'subtitle' => array_key_exists('subtitle', $row) ? $row['subtitle'] : null,
                'slug' => $slug,
                'description' => $row['desc'],
                'specifications' => $row['specs'],
                'show_details' => true,
                'show_specifications' => true,
                'status' => ProductStatus::Active,
                'price_amount' => $row['price'],
                'currency' => 'EUR',
                'image_path' => $primaryPath,
                'category_id' => $row['category']->id,
            ]);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $primaryPath,
                'sort' => 0,
            ]);

            foreach (array_slice($photos, 1) as $sort => $photoUrl) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $this->storePhoto($slug.'-'.($sort + 2), $photoUrl),
                    'sort' => $sort + 1,
                ]);
            }
        }

        $about = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'body' => "Demo electronics storefront for Agovena local development.\n\nReplace this with your own story.",
            'status' => 'published',
        ]);
        $terms = Page::query()->create([
            'title' => 'Terms',
            'slug' => 'terms',
            'body' => 'Demo terms page. Publish your own legal copy before going live.',
            'status' => 'published',
        ]);
        $privacy = Page::query()->create([
            'title' => 'Privacy',
            'slug' => 'privacy',
            'body' => 'Demo privacy page. Publish your own privacy policy before going live.',
            'status' => 'published',
        ]);

        $header = Menu::query()->firstOrCreate(['handle' => 'header'], ['name' => 'Header']);
        $footer = Menu::query()->firstOrCreate(['handle' => 'footer'], ['name' => 'Footer']);
        $legal = Menu::query()->firstOrCreate(['handle' => 'footer_legal'], ['name' => 'Footer legal']);

        MenuItem::query()->whereIn('menu_id', [$header->id, $footer->id, $legal->id])->delete();

        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'Deals', 'type' => 'url', 'url' => '/#catalog', 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'About', 'type' => 'page', 'page_id' => $about->id, 'sort' => 1]);
        MenuItem::query()->create(['menu_id' => $footer->id, 'label' => 'Cart', 'type' => 'url', 'url' => '/cart', 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Terms', 'type' => 'page', 'page_id' => $terms->id, 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Privacy', 'type' => 'page', 'page_id' => $privacy->id, 'sort' => 1]);

        $this->info('Demo catalog seeded with photo assets, categories, products, pages, and menus.');

        return self::SUCCESS;
    }

    private function storePhoto(string $slug, string $url): string
    {
        $relative = 'demo/'.$slug.'.jpg';

        try {
            $response = Http::timeout(25)
                ->withoutVerifying()
                ->withHeaders([
                    'Accept' => 'image/*',
                    'User-Agent' => 'AgovenaLocalDemoSeeder/1.0',
                ])
                ->get($url);

            $body = $response->body();
            if ($response->successful() && strlen($body) > 1000 && @getimagesizefromstring($body) !== false) {
                Storage::disk('public')->put($relative, $body);

                return $relative;
            }
        } catch (\Throwable) {
            // Use the bundled placeholder so the storefront never emits a broken image.
        }

        Storage::disk('public')->put($relative, $this->fallbackJpeg());

        return $relative;
    }

    private function fallbackJpeg(): string
    {
        $placeholder = resource_path('images/demo-placeholder.jpg');
        $bytes = is_file($placeholder) ? (string) file_get_contents($placeholder) : '';
        if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new \RuntimeException('Bundled demo placeholder image is missing or unreadable.');
        }

        return $bytes;
    }
}
