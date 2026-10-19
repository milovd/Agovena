<?php

declare(strict_types=1);

use App\Enums\ProductOptionType;
use App\Livewire\Admin\Discounts\Index as DiscountsIndex;
use App\Livewire\Admin\Products\OptionsEditor;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ProductOption;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * The Admin layout is not re-rendered by Livewire actions. A component that flashes feedback
 * without redirecting must render it itself; otherwise the message is never shown and then
 * appears on whatever full page the staff member opens next.
 */

test('saving and deleting a discount shows the result in the same response', function (): void {
    $component = Livewire::actingAs($this->createStaff())
        ->test(DiscountsIndex::class)
        ->call('create')
        ->set('code', 'FEEDBACK10')
        ->set('value', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('admin.discounts.created'));

    $component->call('deleteDiscount', DiscountCode::query()->where('code', 'FEEDBACK10')->value('id'))
        ->assertSee(__('admin.discounts.deleted'));
});

test('a flashed message is shown once on a full page load, not by both the page and the layout', function (string $route): void {
    $html = $this->actingAs($this->createStaff())
        ->withSession(['status' => 'QA feedback once', 'error' => 'QA problem once'])
        ->get(route($route))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'QA feedback once'))->toBe(1)
        ->and(substr_count($html, 'QA problem once'))->toBe(1);
})->with([
    'page without its own feedback before' => 'admin.discounts.index',
    'page that already rendered feedback' => 'admin.currencies.index',
    'page without any Livewire feedback' => 'admin.dashboard',
]);

test('every Admin component that flashes without redirecting renders the shared feedback', function (): void {
    $missing = [];
    foreach (new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Livewire/Admin'))), '/\.php$/') as $file) {
        $source = (string) file_get_contents($file->getPathname());
        $flashesInPlace = false;
        foreach (preg_split('/\n    (?:public|protected|private) function /', $source) ?: [] as $method) {
            if (str_contains($method, 'session()->flash(') && ! str_contains($method, 'redirect')) {
                $flashesInPlace = true;
            }
        }
        if (! $flashesInPlace) {
            continue;
        }

        preg_match_all("/view\\(\\s*'(livewire\\.admin\\.[^']+)'/", $source, $views);
        foreach (array_unique($views[1]) as $view) {
            $blade = (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php'));
            if (! str_contains($blade, '<x-ag.flash />') && ! str_contains($blade, "session('status')")) {
                $missing[] = $view;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('error feedback uses the defined danger alert style', function (): void {
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    $undefined = [];
    foreach (new RegexIterator($views, '/\.blade\.php$/') as $file) {
        if (str_contains((string) file_get_contents($file->getPathname()), 'ag-alert--error')) {
            $undefined[] = str_replace(resource_path('views/'), '', $file->getPathname());
        }
    }

    expect($undefined)->toBe([]);
});

test('a message flashed before opening the product editor is shown once despite nested editor sections', function (): void {
    $product = Product::factory()->create();

    $html = $this->actingAs($this->createStaff())
        ->withSession(['status' => 'QA product feedback once'])
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('section-options', false)
        ->getContent();

    expect(substr_count($html, 'QA product feedback once'))->toBe(1);
});

test('the nested product options section shows its own result after saving and deleting', function (): void {
    $product = Product::factory()->create();

    $editor = Livewire::actingAs($this->createStaff())
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->set('key', 'region')
        ->set('label', 'Region')
        ->set('type', ProductOptionType::Select->value)
        ->set('choicesText', "eu:Europe\nus:United States")
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('admin.product_options.created'));

    $editor->call('deleteOption', ProductOption::query()->where('product_id', $product->id)->value('id'))
        ->assertSee(__('admin.product_options.deleted'));

    expect(session('status'))->toBeNull();
});
