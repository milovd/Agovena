<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Catalog\UpdateProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class Edit extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Product $product;

    public string $name = '';

    public string $subtitle = '';

    public string $slug = '';

    public string $description = '';

    /** @var list<array{label: string, value: string}> */
    public array $specRows = [
        ['label' => '', 'value' => ''],
    ];

    public bool $show_details = true;

    public bool $show_specifications = true;

    public string $status = 'draft';

    public string $price_amount = '0';

    public string $currency = 'EUR';

    public ?int $category_id = null;

    /** @var list<TemporaryUploadedFile>|TemporaryUploadedFile|null */
    public $uploads = null;

    public function mount(Product $product): void
    {
        $this->authorize('products.update');

        $this->product = $product->load('images');
        $this->name = $product->name;
        $this->subtitle = (string) $product->subtitle;
        $this->slug = $product->slug;
        $this->description = (string) $product->description;
        $this->show_details = (bool) $product->show_details;
        $this->show_specifications = (bool) $product->show_specifications;
        $this->status = $product->status->value;
        $this->price_amount = (string) $product->price_amount;
        $this->currency = $product->currency;
        $this->category_id = $product->category_id;

        /** @var list<array{label: string, value: string}> $specs */
        $specs = $product->specifications ?? [];
        $this->specRows = $specs === []
            ? [['label' => '', 'value' => '']]
            : array_map(static fn (array $row): array => [
                'label' => $row['label'],
                'value' => $row['value'],
            ], $specs);
    }

    public function addSpecRow(): void
    {
        $this->specRows[] = ['label' => '', 'value' => ''];
    }

    public function removeSpecRow(int $index): void
    {
        unset($this->specRows[$index]);
        $this->specRows = array_values($this->specRows);
        if ($this->specRows === []) {
            $this->specRows = [['label' => '', 'value' => '']];
        }
    }

    public function updatedUploads(): void
    {
        $this->authorize('products.update');

        $this->validate([
            'uploads' => ['required'],
            'uploads.*' => ['image', 'max:4096'],
        ]);

        $files = is_array($this->uploads) ? $this->uploads : [$this->uploads];
        $sort = (int) $this->product->images()->max('sort');

        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }
            $path = $file->store('products/'.$this->product->id, 'public');
            $sort++;
            ProductImage::query()->create([
                'product_id' => $this->product->id,
                'path' => $path,
                'sort' => $sort,
            ]);

            if (blank($this->product->image_path)) {
                $this->product->forceFill(['image_path' => $path])->save();
            }
        }

        $this->uploads = null;
        $this->product->refresh()->load('images');
        session()->flash('status', 'Photos uploaded.');
    }

    public function removeImage(int $imageId): void
    {
        $this->authorize('products.update');

        $image = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        Storage::disk('public')->delete($image->path);
        $wasPrimary = $this->product->image_path === $image->path;
        $image->delete();

        if ($wasPrimary) {
            $next = $this->product->images()->orderBy('sort')->first();
            $this->product->forceFill(['image_path' => $next?->path])->save();
        }

        $this->product->refresh()->load('images');
        session()->flash('status', 'Photo removed.');
    }

    public function save(UpdateProduct $update): void
    {
        $this->authorize('products.update');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->product->id)],
            'description' => ['nullable', 'string'],
            'specRows' => ['array'],
            'specRows.*.label' => ['nullable', 'string', 'max:120'],
            'specRows.*.value' => ['nullable', 'string', 'max:255'],
            'show_details' => ['boolean'],
            'show_specifications' => ['boolean'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $update->handle($this->product, [
            'name' => $data['name'],
            'subtitle' => $data['subtitle'] ?: null,
            'slug' => $data['slug'] ?: null,
            'description' => $data['description'] ?: null,
            'specifications' => $data['specRows'],
            'show_details' => (bool) $data['show_details'],
            'show_specifications' => (bool) $data['show_specifications'],
            'status' => $data['status'],
            'price_amount' => (int) $data['price_amount'],
            'currency' => $data['currency'],
            'category_id' => $data['category_id'],
        ]);

        $this->product->refresh()->load('images');
        session()->flash('status', 'Product updated.');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'mode' => 'edit',
            'galleryImages' => $this->product->images,
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => 'Edit product',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
