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

        $this->writePromoAssets();

        $categories = [
            ['name' => 'Apparel', 'slug' => 'apparel', 'description' => 'Clothing and everyday wear.', 'color' => '#155EEF'],
            ['name' => 'Home', 'slug' => 'home', 'description' => 'Physical goods for living spaces.', 'color' => '#0F766E'],
            ['name' => 'Digital', 'slug' => 'digital', 'description' => 'Downloads and digital licenses.', 'color' => '#7C3AED'],
            ['name' => 'Services', 'slug' => 'services', 'description' => 'Bookable and subscription-style services.', 'color' => '#0369A1'],
        ];

        $categoryModels = [];
        foreach ($categories as $row) {
            $image = $this->writePlaceholderImage('category-'.$row['slug'], $row['name'], $row['color'], '1200', '750');
            $categoryModels[$row['slug']] = Category::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'image_path' => $image,
                'is_active' => true,
            ]);
        }

        $products = [
            ['name' => 'Linen Overshirt', 'category' => 'apparel', 'price' => 7900, 'desc' => 'Relaxed fit overshirt in washed linen.', 'color' => '#1E3A8A'],
            ['name' => 'Merino Crew', 'category' => 'apparel', 'price' => 6400, 'desc' => 'Fine-gauge crew neck for layering.', 'color' => '#334155'],
            ['name' => 'Canvas Tote', 'category' => 'apparel', 'price' => 2800, 'desc' => 'Heavy canvas tote with reinforced handles.', 'color' => '#475569'],
            ['name' => 'Wireless Earbuds', 'category' => 'home', 'price' => 12900, 'desc' => 'Compact earbuds with clear everyday sound.', 'color' => '#0F172A'],
            ['name' => 'Ceramic Pour-Over Set', 'category' => 'home', 'price' => 4500, 'desc' => 'Stoneware dripper and mug.', 'color' => '#9A3412'],
            ['name' => 'Oak Desk Tray', 'category' => 'home', 'price' => 3200, 'desc' => 'Solid oak tray for small essentials.', 'color' => '#78350F'],
            ['name' => 'Pattern Library License', 'category' => 'digital', 'price' => 4900, 'desc' => 'Commercial seamless pattern pack.', 'color' => '#5B21B6'],
            ['name' => 'Icon Pack Outline', 'category' => 'digital', 'price' => 1900, 'desc' => '120 outline icons in SVG.', 'color' => '#6D28D9'],
            ['name' => 'Starter Theme Kit', 'category' => 'digital', 'price' => 12900, 'desc' => 'Design tokens and layout starters.', 'color' => '#155EEF'],
            ['name' => 'Setup Consultation', 'category' => 'services', 'price' => 15000, 'desc' => '90-minute remote storefront session.', 'color' => '#0E7490'],
            ['name' => 'Managed Updates Monthly', 'category' => 'services', 'price' => 9900, 'desc' => 'Monthly updates and health checks.', 'color' => '#0369A1'],
            ['name' => 'Content Migration', 'category' => 'services', 'price' => 24900, 'desc' => 'Assisted catalog and page migration.', 'color' => '#1D4ED8'],
        ];

        foreach ($products as $i => $row) {
            $slug = Str::slug($row['name']);
            $path = $this->writePlaceholderImage($slug, $row['name'], $row['color']);

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

            if ($i < 4) {
                $alt = $this->writePlaceholderImage($slug.'-alt', $row['name'].' detail', '#1249C7');
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

        $header = Menu::query()->firstOrCreate(['handle' => 'header'], ['name' => 'Header']);
        $footer = Menu::query()->firstOrCreate(['handle' => 'footer'], ['name' => 'Footer']);
        $legal = Menu::query()->firstOrCreate(['handle' => 'footer_legal'], ['name' => 'Footer legal']);

        MenuItem::query()->whereIn('menu_id', [$header->id, $footer->id, $legal->id])->delete();

        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'Shop', 'type' => 'url', 'url' => '/', 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'About', 'type' => 'page', 'page_id' => $about->id, 'sort' => 1]);
        MenuItem::query()->create(['menu_id' => $footer->id, 'label' => 'Shop', 'type' => 'url', 'url' => '/', 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $footer->id, 'label' => 'Cart', 'type' => 'url', 'url' => '/cart', 'sort' => 1]);
        MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Terms', 'type' => 'page', 'page_id' => $terms->id, 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Privacy', 'type' => 'page', 'page_id' => $privacy->id, 'sort' => 1]);

        $this->info('Demo catalog seeded: '.count($categories).' categories, '.count($products).' products, pages, and menus.');

        return self::SUCCESS;
    }

    private function writePromoAssets(): void
    {
        $this->writePlaceholderImage('hero-promo', 'Featured collection', '#155EEF', '1200', '900');
        $this->writePlaceholderImage('promo-split', 'Every kind of shop', '#0B1220', '1200', '900');
    }

    private function writePlaceholderImage(
        string $slug,
        string $label,
        string $hex,
        string $width = '800',
        string $height = '800',
    ): string {
        $relative = 'demo/'.$slug.'.svg';
        $safe = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);
        $w = (int) $width;
        $h = (int) $height;
        $innerW = $w - 96;
        $innerH = $h - 96;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$hex}"/>
      <stop offset="100%" stop-color="#0B1220"/>
    </linearGradient>
  </defs>
  <rect width="{$w}" height="{$h}" fill="url(#g)"/>
  <rect x="48" y="48" width="{$innerW}" height="{$innerH}" fill="none" stroke="rgba(255,255,255,0.28)" stroke-width="2"/>
  <text x="50%" y="48%" text-anchor="middle" fill="#ffffff" font-family="DM Sans, Segoe UI, sans-serif" font-size="36" font-weight="700">{$safe}</text>
  <text x="50%" y="56%" text-anchor="middle" fill="rgba(255,255,255,0.72)" font-family="DM Sans, Segoe UI, sans-serif" font-size="16">Demo asset</text>
</svg>
SVG;
        Storage::disk('public')->put($relative, $svg);

        return $relative;
    }
}
