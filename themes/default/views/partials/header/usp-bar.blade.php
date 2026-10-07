{{-- Announcement / USP bar above the header. Expects $uspItems from theme::partials.header. --}}
@if ($uspItems !== [])
    @php
        $uspBenefits = [];
        $uspCtas = [];
        foreach ($uspItems as $usp) {
            if ($usp['highlight']) {
                $uspCtas[] = $usp;
            } else {
                $uspBenefits[] = $usp;
            }
        }
    @endphp
    <div class="store-usp" role="region" aria-label="{{ __('storefront.benefits_aria') }}">
        <div class="store-usp__inner">
            @if ($uspBenefits !== [])
                <ul class="store-usp__benefits">
                    @foreach ($uspBenefits as $usp)
                        <li class="store-usp__item">
                            @if ($usp['href'] !== '')
                                <a class="store-usp__link" href="{{ $usp['href'] }}">
                                    @include('theme::partials.header.usp-label', ['usp' => $usp])
                                </a>
                            @else
                                <span class="store-usp__text">
                                    @include('theme::partials.header.usp-label', ['usp' => $usp])
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($uspCtas !== [])
                <ul class="store-usp__actions">
                    @foreach ($uspCtas as $usp)
                        @php
                            $ctaHref = $usp['href'] !== '' ? $usp['href'] : route('storefront.home');
                        @endphp
                        <li class="store-usp__item store-usp__item--cta">
                            <a class="store-usp__cta" href="{{ $ctaHref }}">
                                @include('theme::partials.header.usp-label', ['usp' => $usp])
                                <span class="store-usp__chev" aria-hidden="true">›</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
