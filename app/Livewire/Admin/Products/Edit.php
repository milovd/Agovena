<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\DeleteProduct;
use App\Agovena\Catalog\UpdateProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public string $sku = '';

    public string $description = '';

    /** @var list<array{label: string, value: string}> */
    public array $specRows = [
        ['label' => '', 'value' => ''],
    ];

    public bool $show_details = true;

    public bool $show_specifications = true;

    public string $status = 'draft';

    public string $price = '0.00';

    public string $currency = 'EUR';

    public ?int $category_id = null;

    /** @var list<TemporaryUploadedFile>|TemporaryUploadedFile|null */
    public $uploads = null;

    public bool $confirmingDelete = false;

    public function mount(Product $product): void
    {
        $this->authorize('products.update');

        $this->product = $product->load('images');
        $this->name = $product->name;
        $this->subtitle = (string) $product->subtitle;
        $this->slug = $product->slug;
        $this->sku = (string) $product->sku;
        $this->description = (string) $product->description;
        $this->show_details = (bool) $product->show_details;
        $this->show_specifications = (bool) $product->show_specifications;
        $this->status = $product->status->value;
        $this->price = MoneyFormatter::majorInputFromMinor($product->price_amount, $product->currency);
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
            'uploads.*' => ['image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
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

    public function setPrimaryImage(int $imageId): void
    {
        $this->authorize('products.update');

        $image = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        $this->product->forceFill(['image_path' => $image->path])->save();
        $this->product->refresh()->load('images');
        session()->flash('status', 'Primary photo updated.');
    }

    public function moveImage(int $imageId, string $direction): void
    {
        $this->authorize('products.update');

        $image = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        $swap = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('sort', '<', $image->sort)->orderByDesc('sort'),
                fn ($q) => $q->where('sort', '>', $image->sort)->orderBy('sort'),
            )
            ->first();

        if ($swap === null) {
            return;
        }

        DB::transaction(function () use ($image, $swap): void {
            $current = $image->sort;
            $image->forceFill(['sort' => $swap->sort])->save();
            $swap->forceFill(['sort' => $current])->save();
        });

        $this->product->refresh()->load('images');
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

        $currencyRule = Currency::query()->where('is_active', true)->exists()
            ? Rule::exists('currencies', 'code')->where('is_active', true)
            : ['string', 'size:3'];

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->product->id)],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->product->id)],
            'description' => ['nullable', 'string'],
            'specRows' => ['array'],
            'specRows.*.label' => ['nullable', 'string', 'max:120'],
            'specRows.*.value' => ['nullable', 'string', 'max:255'],
            'show_details' => ['boolean'],
            'show_specifications' => ['boolean'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price' => ['required', 'string', 'max:20'],
            'currency' => ['required', $currencyRule],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        try {
            $priceAmount = MoneyFormatter::minorFromMajorInput($data['price'], $data['currency']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => $e->getMessage(),
            ]);
        }

        $update->handle($this->product, [
            'name' => $data['name'],
            'subtitle' => $data['subtitle'] ?: null,
            'slug' => $data['slug'] ?: null,
            'sku' => $data['sku'] ?: null,
            'description' => $data['description'] ?: null,
            'specifications' => $data['specRows'],
            'show_details' => (bool) $data['show_details'],
            'show_specifications' => (bool) $data['show_specifications'],
            'status' => $data['status'],
            'price_amount' => $priceAmount,
            'currency' => $data['currency'],
            'category_id' => $data['category_id'],
        ]);

        $this->product->refresh()->load('images');
        session()->flash('status', 'Product updated.');
    }

    public function setDraft(): void
    {
        $this->authorize('products.update');

        $this->product->forceFill([
            'status' => ProductStatus::Draft,
        ])->save();

        $this->status = ProductStatus::Draft->value;
        session()->flash('status', 'Product set to draft.');
    }

    public function confirmDelete(): void
    {
        $this->authorize('products.delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteProduct(DeleteProduct $delete): void
    {
        $this->authorize('products.delete');

        try {
            $delete->handle($this->product);
            session()->flash('status', 'Product deleted.');
            $this->redirect(route('admin.products.index'), navigate: true);
        } catch (ValidationException $e) {
            $this->confirmingDelete = false;
            session()->flash('error', $e->errors()['product'][0] ?? $e->getMessage());
        }
    }

    public function render(AdminRegistrar $admin, DeleteProduct $delete)
    {
        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'mode' => 'edit',
            'galleryImages' => $this->product->images,
            'isReferenced' => $delete->isReferencedByOrders($this->product),
        ])->layout('layouts.admin', [
            'title' => 'Edit product',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
