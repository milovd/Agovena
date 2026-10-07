{{-- Five-star rating glyphs. Expects $rating (float). --}}
@for ($i = 1; $i <= 5; $i++)
    <svg class="store-product__star {{ $i <= (int) round($rating) ? 'is-filled' : '' }}" width="16" height="16" viewBox="0 0 24 24" focusable="false">
        <path d="M12 3.5 14.7 9l6 .9-4.4 4.2 1 6L12 17.8 6.7 20.1l1-6L3.3 9.9l6-.9L12 3.5z"/>
    </svg>
@endfor
