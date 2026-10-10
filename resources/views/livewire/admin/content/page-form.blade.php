<div class="admin-page">
    <x-ag.page-header :heading="__('admin.content.pages.'.($pageId === null ? 'new' : 'edit'))">
        <x-slot:back><x-ag.back :href="route('admin.appearance.pages')" :label="__('admin.content.pages.title')" /></x-slot:back>
    </x-ag.page-header>

    <section class="admin-panel" aria-label="{{ __('admin.content.pages.'.($pageId === null ? 'new' : 'edit')) }}">
        <form class="ag-form" wire:submit="save">
            <div class="ag-field">
                <label class="ag-field__label" for="page-title">{{ __('common.title') }}</label>
                <input id="page-title" class="ag-input" type="text" wire:model="title" required>
                @error('title') <p class="ag-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="page-slug">{{ __('admin.content.pages.slug') }}</label>
                <input id="page-slug" class="ag-input" type="text" wire:model="slug">
                @error('slug') <p class="ag-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field" x-data="agPageEditor" data-initial-body="{{ $body }}" x-on:page-image-uploaded.window="insertImage($event.detail.url, $event.detail.alt)" data-link-prompt="{{ __('admin.content.pages.editor.link_prompt') }}" data-link-new-tab-prompt="{{ __('admin.content.pages.editor.link_new_tab_prompt') }}">
                <span class="ag-field__label" id="page-editor-label">{{ __('admin.content.pages.body') }}</span>
                <div class="ag-page-editor">
                    <div class="ag-page-editor__toolbar" x-show="editor" x-cloak role="toolbar" aria-label="{{ __('admin.content.pages.editor.toolbar') }}">
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleHeading(2)" aria-label="{{ __('admin.content.pages.editor.h2') }}">H2</button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleHeading(3)" aria-label="{{ __('admin.content.pages.editor.h3') }}">H3</button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleHeading(4)" aria-label="{{ __('admin.content.pages.editor.h4') }}">H4</button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleBold()" aria-label="{{ __('admin.content.pages.editor.bold') }}"><strong>B</strong></button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleItalic()" aria-label="{{ __('admin.content.pages.editor.italic') }}"><em>I</em></button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleList('bullet')" aria-label="{{ __('admin.content.pages.editor.bullets') }}">&bull; {{ __('admin.content.pages.editor.list') }}</button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="toggleList('ordered')" aria-label="{{ __('admin.content.pages.editor.numbers') }}">1. {{ __('admin.content.pages.editor.list') }}</button>
                        <button type="button" class="ag-btn ag-btn--ghost" x-show="mode === 'visual'" x-on:click="setLink()">{{ __('admin.content.pages.editor.link') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" x-on:click="toggleSource()" x-text="mode === 'visual' ? @js(__('admin.content.pages.editor.html')) : @js(__('admin.content.pages.editor.visual'))"></button>
                    </div>
                    <div wire:ignore x-show="mode === 'visual'">
                        <div id="page-editor" x-ref="canvas" class="ag-page-editor__canvas" aria-label="{{ __('admin.content.pages.body') }}"></div>
                    </div>
                    <textarea class="ag-input ag-page-editor__source" x-show="mode === 'html'" x-cloak x-model="content" x-on:input="updateSource()" aria-label="{{ __('admin.content.pages.editor.html') }}" rows="12"></textarea>
                </div>
                <div class="ag-page-editor__upload">
                    <div class="ag-field">
                        <label class="ag-field__label" for="page-image">{{ __('admin.content.pages.editor.image') }}</label>
                        <input id="page-image" class="ag-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="image">
                        @error('image') <p class="ag-field__error">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="page-image-alt">{{ __('admin.content.pages.editor.image_alt') }}</label>
                        <input id="page-image-alt" class="ag-input" type="text" maxlength="160" wire:model="imageAlt">
                        @error('imageAlt') <p class="ag-field__error">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="uploadImage" x-bind:disabled="!editor" wire:loading.attr="disabled" wire:target="image,uploadImage">{{ __('admin.content.pages.editor.insert_image') }}</button>
                </div>
                @error('body') <p class="ag-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="page-status">{{ __('common.status') }}</label>
                <select id="page-status" class="ag-select" wire:model="status">
                    <option value="draft">{{ __('admin.content.pages.draft') }}</option>
                    <option value="published">{{ __('admin.content.pages.published') }}</option>
                </select>
                @error('status') <p class="ag-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="ag-toolbar">
                <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">{{ __('common.save') }}</button>
                <a class="ag-btn ag-btn--secondary" href="{{ route('admin.appearance.pages') }}">{{ __('common.cancel') }}</a>
            </div>
        </form>
    </section>
</div>
