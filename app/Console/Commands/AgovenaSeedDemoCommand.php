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

        $phones = Category::query()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'description' => 'Smartphones for everyday use.',
            'image_path' => $this->writeDeviceImage('category-phones', 'Phones', '#155EEF'),
            'is_active' => true,
        ]);
        $audio = Category::query()->create([
            'name' => 'Audio',
            'slug' => 'audio',
            'description' => 'Headphones and earbuds.',
            'image_path' => $this->writeDeviceImage('category-audio', 'Audio', '#0F766E', 'earbuds'),
            'is_active' => true,
        ]);
        $accessories = Category::query()->create([
            'name' => 'Accessories',
            'slug' => 'accessories',
            'description' => 'Cases, chargers, and everyday extras.',
            'image_path' => $this->writeDeviceImage('category-accessories', 'Accessories', '#0369A1', 'case'),
            'is_active' => true,
        ]);

        $android = Category::query()->create([
            'parent_id' => $phones->id,
            'name' => 'Android',
            'slug' => 'android',
            'description' => 'Android smartphones.',
            'image_path' => $this->writeDeviceImage('category-android', 'Android', '#1249C7'),
            'is_active' => true,
        ]);
        $iphone = Category::query()->create([
            'parent_id' => $phones->id,
            'name' => 'iPhone',
            'slug' => 'iphone',
            'description' => 'iPhone models.',
            'image_path' => $this->writeDeviceImage('category-iphone', 'iPhone', '#0B1220'),
            'is_active' => true,
        ]);

        $products = [
            ['name' => 'Nova Phone 14', 'category' => $android, 'price' => 69900, 'desc' => '6.1" OLED display, dual camera, all-day battery.', 'color' => '#155EEF', 'shape' => 'phone'],
            ['name' => 'Nova Phone 14 Pro', 'category' => $android, 'price' => 89900, 'desc' => 'Pro camera system with bright AMOLED panel.', 'color' => '#0F172A', 'shape' => 'phone'],
            ['name' => 'Pulse X', 'category' => $android, 'price' => 54900, 'desc' => 'Compact Android phone with fast charging.', 'color' => '#1D4ED8', 'shape' => 'phone'],
            ['name' => 'iPhone 15', 'category' => $iphone, 'price' => 92900, 'desc' => 'A16 performance in a slim aluminum design.', 'color' => '#334155', 'shape' => 'phone'],
            ['name' => 'iPhone 15 Pro', 'category' => $iphone, 'price' => 119900, 'desc' => 'Titanium frame and advanced camera controls.', 'color' => '#0B1220', 'shape' => 'phone'],
            ['name' => 'Air Soft Buds', 'category' => $audio, 'price' => 12900, 'desc' => 'Lightweight earbuds with clear everyday sound.', 'color' => '#0F766E', 'shape' => 'earbuds'],
            ['name' => 'Studio Max Headphones', 'category' => $audio, 'price' => 34900, 'desc' => 'Over-ear headphones with balanced sound.', 'color' => '#134E4A', 'shape' => 'headphones'],
            ['name' => 'Clip Buds Mini', 'category' => $audio, 'price' => 7900, 'desc' => 'Compact buds for calls and commuting.', 'color' => '#115E59', 'shape' => 'earbuds'],
            ['name' => 'Clear Case MagSafe', 'category' => $accessories, 'price' => 2900, 'desc' => 'Protective clear case with MagSafe ring.', 'color' => '#0369A1', 'shape' => 'case'],
            ['name' => '40W GaN Charger', 'category' => $accessories, 'price' => 3900, 'desc' => 'Compact dual-port USB-C charger.', 'color' => '#0C4A6E', 'shape' => 'charger'],
            ['name' => 'Braided USB-C Cable', 'category' => $accessories, 'price' => 1900, 'desc' => '2m braided cable for phones and earbuds.', 'color' => '#075985', 'shape' => 'cable'],
            ['name' => 'Desk Stand Aluminum', 'category' => $accessories, 'price' => 4500, 'desc' => 'Angled aluminum stand for phones.', 'color' => '#64748B', 'shape' => 'stand'],
        ];

        foreach ($products as $i => $row) {
            $slug = Str::slug($row['name']);
            $path = $this->writeDeviceImage($slug, $row['name'], $row['color'], $row['shape']);

            $product = Product::query()->create([
                'name' => $row['name'],
                'slug' => $slug,
                'description' => $row['desc'],
                'status' => ProductStatus::Active,
                'price_amount' => $row['price'],
                'currency' => 'EUR',
                'image_path' => $path,
                'category_id' => $row['category']->id,
            ]);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'sort' => 0,
            ]);

            if ($i < 5) {
                $alt = $this->writeDeviceImage($slug.'-alt', $row['name'].' detail', '#1249C7', $row['shape']);
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

        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'Shop', 'type' => 'url', 'url' => '/', 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'Deals', 'type' => 'url', 'url' => '/#catalog', 'sort' => 1]);
        MenuItem::query()->create(['menu_id' => $header->id, 'label' => 'About', 'type' => 'page', 'page_id' => $about->id, 'sort' => 2]);
        MenuItem::query()->create(['menu_id' => $footer->id, 'label' => 'Shop', 'type' => 'url', 'url' => '/', 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $footer->id, 'label' => 'Cart', 'type' => 'url', 'url' => '/cart', 'sort' => 1]);
        MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Terms', 'type' => 'page', 'page_id' => $terms->id, 'sort' => 0]);
        MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Privacy', 'type' => 'page', 'page_id' => $privacy->id, 'sort' => 1]);

        $this->info('Demo catalog seeded: phones-focused categories, products, pages, and menus.');

        return self::SUCCESS;
    }

    private function writePromoAssets(): void
    {
        $this->writeDeviceImage('hero-promo', 'New phones', '#155EEF', 'phone', '1200', '900');
        $this->writeDeviceImage('promo-split', 'Every kind of shop', '#0B1220', 'headphones', '1200', '900');
    }

    private function writeDeviceImage(
        string $slug,
        string $label,
        string $hex,
        string $shape = 'phone',
        string $width = '800',
        string $height = '800',
    ): string {
        $relative = 'demo/'.$slug.'.svg';
        $safe = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);
        $w = (int) $width;
        $h = (int) $height;
        $device = $this->deviceMarkup($shape, $w, $h);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$hex}"/>
      <stop offset="100%" stop-color="#0B1220"/>
    </linearGradient>
  </defs>
  <rect width="{$w}" height="{$h}" rx="28" fill="url(#bg)"/>
  {$device}
  <text x="50%" y="88%" text-anchor="middle" fill="#ffffff" font-family="DM Sans, Segoe UI, sans-serif" font-size="28" font-weight="700">{$safe}</text>
</svg>
SVG;
        Storage::disk('public')->put($relative, $svg);

        return $relative;
    }

    private function deviceMarkup(string $shape, int $w, int $h): string
    {
        $cx = (int) ($w / 2);
        $cy = (int) ($h * 0.42);
        $left = $cx - 70;
        $top = $cy - 130;
        $earLeft = $cx - 90;
        $earRight = $cx + 90;
        $canLeft = $cx - 110;
        $canRight = $cx + 70;
        $archY = $cy;
        $budY = $cy + 20;
        $stemY = $cy + 48;

        return match ($shape) {
            'earbuds' => <<<SVG
  <g fill="none" stroke="rgba(255,255,255,0.92)" stroke-width="10" stroke-linecap="round">
    <circle cx="{$cx}" cy="{$cy}" r="48" fill="rgba(255,255,255,0.12)"/>
    <path d="M{$cx} {$stemY} v70"/>
    <circle cx="{$earLeft}" cy="{$budY}" r="34" fill="rgba(255,255,255,0.12)"/>
    <circle cx="{$earRight}" cy="{$budY}" r="34" fill="rgba(255,255,255,0.12)"/>
  </g>
SVG,
            'headphones' => <<<SVG
  <g fill="none" stroke="rgba(255,255,255,0.92)" stroke-width="12" stroke-linecap="round">
    <path d="M{$earLeft} {$archY} a90 70 0 0 1 180 0"/>
    <rect x="{$canLeft}" y="{$archY}" width="40" height="70" rx="14" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.92)"/>
    <rect x="{$canRight}" y="{$archY}" width="40" height="70" rx="14" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.92)"/>
  </g>
SVG,
            'case' => <<<SVG
  <rect x="{$left}" y="{$top}" width="140" height="240" rx="28" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.9)" stroke-width="8"/>
  <rect x="{$cx}" y="{$cy}" width="96" height="170" rx="16" fill="rgba(15,23,42,0.35)" transform="translate(-48 -88)"/>
SVG,
            'charger' => <<<SVG
  <rect x="{$cx}" y="{$cy}" width="110" height="110" rx="22" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.9)" stroke-width="8" transform="translate(-55 -55)"/>
  <circle cx="{$cx}" cy="{$cy}" r="18" fill="rgba(255,255,255,0.85)"/>
SVG,
            'cable' => <<<SVG
  <path d="M{$earLeft} {$cy} C{$cx} {$top}, {$cx} {$stemY}, {$earRight} {$cy}" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="14" stroke-linecap="round"/>
  <rect x="{$earLeft}" y="{$cy}" width="36" height="36" rx="8" fill="rgba(255,255,255,0.85)" transform="translate(-25 -18)"/>
  <rect x="{$earRight}" y="{$cy}" width="36" height="36" rx="8" fill="rgba(255,255,255,0.85)" transform="translate(-11 -18)"/>
SVG,
            'stand' => <<<SVG
  <path d="M{$earLeft} {$stemY} L{$cx} {$top} L{$earRight} {$stemY} Z" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.9)" stroke-width="8" stroke-linejoin="round"/>
SVG,
            default => <<<SVG
  <rect x="{$left}" y="{$top}" width="140" height="260" rx="28" fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.92)" stroke-width="8"/>
  <rect x="{$cx}" y="{$cy}" width="104" height="200" rx="12" fill="rgba(15,23,42,0.35)" transform="translate(-52 -108)"/>
  <rect x="{$cx}" y="{$top}" width="36" height="8" rx="4" fill="rgba(255,255,255,0.55)" transform="translate(-18 12)"/>
SVG,
        };
    }
}
