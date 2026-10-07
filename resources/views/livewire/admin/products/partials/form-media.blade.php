{{-- Product form (edit mode): media tab. Part of livewire.admin.products.form. --}}
<section id="product-tab-media" class="ag-section ag-form--product" role="tabpanel" x-cloak x-show="activeTab === 'media'" aria-labelledby="section-media">
    <header class="ag-section__header">
        <h3 id="section-media" class="ag-section__title">{{ __('admin.products.form.media') }}</h3>
        <p class="ag-section__lede">{{ __('admin.products.form.media_lede') }}</p>
    </header>
    <div class="ag-section__body">
        <x-ag.file-upload
            id="product-uploads"
            :label="__('admin.products.form.add_photos')"
            :hint="__('admin.products.form.photos_hint')"
            multiple
            :button-label="__('admin.products.form.upload_photos')"
            :replace-label="__('admin.products.form.upload_more')"
            loading-target="uploads"
            wire:model="uploads"
        >
            @error('uploads') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            @error('uploads.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </x-ag.file-upload>

        @if ($galleryImages->isNotEmpty())
            <ul class="ag-gallery-admin" role="list">
                @foreach ($galleryImages as $image)
                    @php $isPrimary = $product->image_path === $image->path; @endphp
                    <li class="ag-media-tile {{ $isPrimary ? 'is-primary' : '' }}" wire:key="img-{{ $image->id }}">
                        <div class="ag-media-tile__preview">
                            @php $previewUrl = \App\Agovena\Media\PublicMedia::url($image->path); @endphp
                            @if ($previewUrl)
                                <img src="{{ $previewUrl }}" alt="" width="112" height="112">
                            @endif
                            @if ($isPrimary)
                                <span class="ag-media-tile__badge">{{ __('admin.products.form.primary_badge') }}</span>
                            @endif
                        </div>
                        <div class="ag-media-tile__toolbar">
                            <div class="ag-media-tile__tools">
                                <button type="button" class="ag-icon-btn" wire:click="moveImage({{ $image->id }}, 'up')" title="{{ __('admin.products.form.move_earlier') }}" aria-label="{{ __('admin.products.form.move_earlier_aria') }}">
                                    <x-ag.icon name="chevron-up" :size="16" />
                                </button>
                                <button type="button" class="ag-icon-btn" wire:click="moveImage({{ $image->id }}, 'down')" title="{{ __('admin.products.form.move_later') }}" aria-label="{{ __('admin.products.form.move_later_aria') }}">
                                    <x-ag.icon name="chevron-down" :size="16" />
                                </button>
                            </div>
                            <div
                                class="ag-menu"
                                x-data="agDisclosure"
                                @keydown.escape.window="close()"
                                @click.outside="close()"
                            >
                                <button
                                    type="button"
                                    class="ag-icon-btn"
                                    @click="toggle()"
                                    :aria-expanded="open.toString()"
                                    aria-haspopup="menu"
                                    title="{{ __('admin.products.form.photo_actions') }}"
                                    aria-label="{{ __('admin.products.form.photo_actions') }}"
                                >
                                    <x-ag.icon name="more-horizontal" :size="16" />
                                </button>
                                <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                                    @unless ($isPrimary)
                                        <button type="button" class="ag-menu__item" role="menuitem" wire:click="setPrimaryImage({{ $image->id }})">
                                            {{ __('admin.products.form.set_primary') }}
                                        </button>
                                    @endunless
                                    <button
                                        type="button"
                                        class="ag-menu__item ag-menu__item--danger"
                                        role="menuitem"
                                        wire:click="removeImage({{ $image->id }})"
                                        wire:confirm="{{ __('admin.products.form.remove_photo_confirm') }}"
                                    >
                                        {{ __('common.remove') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="ag-empty ag-empty--compact" role="status">{{ __('admin.products.form.no_photos') }}</p>
        @endif
    </div>
</section>
