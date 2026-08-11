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

        $categories = [
            ['name' => 'Apparel', 'slug' => 'apparel', 'description' => 'Clothing and everyday wear.'],
            ['name' => 'Home', 'slug' => 'home', 'description' => 'Physical goods for living spaces.'],
            ['name' => 'Digital', 'slug' => 'digital', 'description' => 'Downloads and digital licenses.'],
            ['name' => 'Services', 'slug' => 'services', 'description' => 'Bookable and subscription-style services.'],
        ];

        $categoryModels = [];
        foreach ($categories as $row) {
            $categoryModels[$row['slug']] = Category::query()->create([
                ...$row,
                'is_active' => true,
            ]);
        }

        $products = [
            ['name' => 'Linen Overshirt', 'category' => 'apparel', 'price' => 7900, 'desc' => 'Relaxed fit overshirt in washed linen. Neutral enough for any wardrobe.'],
            ['name' => 'Merino Crew', 'category' => 'apparel', 'price' => 6400, 'desc' => 'Fine-gauge crew neck. Soft, warm, and easy to layer.'],
            ['name' => 'Canvas Tote', 'category' => 'apparel', 'price' => 2800, 'desc' => 'Heavy canvas tote with reinforced handles for daily carry.'],
            ['name' => 'Ceramic Pour-Over Set', 'category' => 'home', 'price' => 4500, 'desc' => 'Stoneware dripper and mug. Matte glaze, dishwasher safe.'],
            ['name' => 'Oak Desk Tray', 'category' => 'home', 'price' => 3200, 'desc' => 'Solid oak tray for keys, cards, and small essentials.'],
            ['name' => 'Wool Throw', 'category' => 'home', 'price' => 8900, 'desc' => 'Lightweight wool throw with clean hemmed edges.'],
            ['name' => 'Pattern Library License', 'category' => 'digital', 'price' => 4900, 'desc' => 'Commercial license for a curated pack of seamless patterns.'],
            ['name' => 'Icon Pack Outline', 'category' => 'digital', 'price' => 1900, 'desc' => '120 outline icons in SVG. Single seat license.'],
            ['name' => 'Starter Theme Kit', 'category' => 'digital', 'price' => 12900, 'desc' => 'Design tokens and layout starters for a commerce storefront.'],
            ['name' => 'Setup Consultation', 'category' => 'services', 'price' => 15000, 'desc' => '90-minute remote session to configure your storefront and catalog.'],
            ['name' => 'Managed Updates Monthly', 'category' => 'services', 'price' => 9900, 'desc' => 'Monthly platform updates and health checks for one store.'],
            ['name' => 'Content Migration', 'category' => 'services', 'price' => 24900, 'desc' => 'Assisted migration of products and pages into your store.'],
        ];

        $palette = ['#1f4e46', '#3d5a80', '#6b4f3a', '#4a5568', '#2c3e50', '#5c6b4a'];

        foreach ($products as $i => $row) {
            $slug = Str::slug($row['name']);
            $color = $palette[$i % count($palette)];
            $path = $this->writePlaceholderImage($slug, $row['name'], $color);

            $product = Product::query()->create([
                'name' => $row['name'],
                'slug' => $slug,
                'description' => $row['desc'],
                'status' => ProductStatus::Active,
                'price_amount' => $row['price'],
                'currency' => 'EUR',
                'image_path' => $path,
                'category_id' => $categoryModels[$row['category']]->id,
            ]);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'sort' => 0,
            ]);

            if ($i < 3) {
                $alt = $this->writePlaceholderImage($slug.'-alt', $row['name'].' detail', $palette[($i + 2) % count($palette)]);
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $alt,
                    'sort' => 1,
                ]);
            }
        }

        $about = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'body' => "This is demo content for local development.\n\nReplace it with your store story.",
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

        $header = Menu::query()->create(['handle' => 'header', 'name' => 'Header']);
        $footer = Menu::query()->create(['handle' => 'footer', 'name' => 'Footer']);
        $legal = Menu::query()->create(['handle' => 'footer_legal', 'name' => 'Footer legal']);

        MenuItem::query()->create([
            'menu_id' => $header->id,
            'label' => 'Shop',
            'type' => 'url',
            'url' => '/',
            'sort' => 0,
        ]);
        MenuItem::query()->create([
            'menu_id' => $header->id,
            'label' => 'About',
            'type' => 'page',
            'page_id' => $about->id,
            'sort' => 1,
        ]);
        MenuItem::query()->create([
            'menu_id' => $footer->id,
            'label' => 'Shop',
            'type' => 'url',
            'url' => '/',
            'sort' => 0,
        ]);
        MenuItem::query()->create([
            'menu_id' => $footer->id,
            'label' => 'Cart',
            'type' => 'url',
            'url' => '/cart',
            'sort' => 1,
        ]);
        MenuItem::query()->create([
            'menu_id' => $legal->id,
            'label' => 'Terms',
            'type' => 'page',
            'page_id' => $terms->id,
            'sort' => 0,
        ]);
        MenuItem::query()->create([
            'menu_id' => $legal->id,
            'label' => 'Privacy',
            'type' => 'page',
            'page_id' => $privacy->id,
            'sort' => 1,
        ]);

        $this->info('Demo catalog seeded: '.count($categories).' categories, '.count($products).' products, pages, and menus.');

        return self::SUCCESS;
    }

    private function writePlaceholderImage(string $slug, string $label, string $hex): string
    {
        $relative = 'demo/'.$slug.'.svg';
        $safe = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600" role="img">
  <rect width="800" height="600" fill="{$hex}"/>
  <rect x="40" y="40" width="720" height="520" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="2"/>
  <text x="400" y="300" text-anchor="middle" fill="#ffffff" font-family="Georgia, serif" font-size="36">{$safe}</text>
  <text x="400" y="350" text-anchor="middle" fill="rgba(255,255,255,0.75)" font-family="sans-serif" font-size="18">Demo product image</text>
</svg>
SVG;
        Storage::disk('public')->put($relative, $svg);

        return $relative;
    }
}
