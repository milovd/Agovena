@php
    $copyKey = 'errors.'.$status;
    $showBackAction = (int) $status === 419;
@endphp
<section class="store-error" data-error-status="{{ $status }}" aria-labelledby="error-heading">
    <h1 id="error-heading" class="store-error__title">{{ __($copyKey.'.heading') }}</h1>

    <div class="store-error__art" aria-hidden="true">
        <span class="store-error__number">{{ $status }}</span>
        <svg class="store-error__illustration" viewBox="0 0 620 320" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse class="store-error__illustration-shadow" cx="310" cy="286" rx="172" ry="16" />
            <path class="store-error__route" d="M102 230C167 172 203 238 263 186C323 134 364 198 422 144C465 104 500 120 537 88" />
            <path class="store-error__route-arrow" d="m521 87 18 1-8 16" />
            <circle class="store-error__route-stop" cx="102" cy="230" r="10" />
            <circle class="store-error__route-stop" cx="537" cy="88" r="10" />

            <g class="store-error__parcel" transform="rotate(-6 293 209)">
                <path d="m214 155 77-32 103 37-78 35-102-40Z" />
                <path d="m214 155 0 91 102 42v-93l-102-40Z" />
                <path d="m394 160-78 35v93l78-42v-86Z" />
                <path class="store-error__parcel-tape" d="m291 123 103 37-78 35-102-40 77-32Z" />
                <path class="store-error__parcel-tape" d="m291 123v72" />
                <path class="store-error__parcel-mark" d="m252 213 35 14m-35 7 35 14" />
            </g>

            <g class="store-error__bag" transform="rotate(9 431 214)">
                <path d="M374 169h116l-8 108H382l-8-108Z" />
                <path d="M400 171c0-28 64-28 64 0" />
                <path class="store-error__bag-mark" d="m413 218 17 18 27-33" />
                <path class="store-error__bag-handle" d="M389 277h82" />
            </g>

            <g class="store-error__pin" transform="translate(142 66)">
                <path d="M26 7C12 7 2 17 2 30c0 17 24 39 24 39s24-22 24-39C50 17 40 7 26 7Z" />
                <circle cx="26" cy="30" r="8" />
            </g>
        </svg>
    </div>

    @if ((int) $status === 404)
        <p class="store-error__description">{{ __($copyKey.'.description') }}</p>
    @endif
    <p class="store-error__lede">{{ __($copyKey.'.lede') }}</p>

    <div class="store-error__actions">
        @if ($showBackAction)
            <a class="store-btn store-btn--primary" href="{{ url()->previous('/') }}">{{ __('errors.actions.back') }}</a>
            <a class="store-btn store-btn--outline" href="{{ url('/') }}">{{ __('errors.actions.home') }}</a>
        @else
            <a class="store-btn store-btn--primary" href="{{ url('/') }}">{{ __('errors.actions.home') }}</a>
        @endif
    </div>
</section>
