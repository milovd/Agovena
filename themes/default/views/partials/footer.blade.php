@php
    $cfg = $themeConfig ?? app(\App\Agovena\Theme\ThemeManager::class)->config();
    $tagline = $cfg->string('footer.tagline', 'Quality products, clear pricing, and a simple shopping experience.');
@endphp

<footer class="store-footer">
    <div class="store-footer__inner">
        <div class="store-footer__brand">
            <p class="store-footer__name">{{ $siteName ?? 'Store' }}</p>
            @if ($tagline !== '')
                <p class="store-footer__tagline">{{ $tagline }}</p>
            @endif
        </div>

        <div class="store-footer__columns">
            <div class="store-footer__col">
                <p class="store-footer__heading">Explore</p>
                <ul class="store-footer__list" role="list">
                    @foreach ($themeFooterNav ?? [] as $item)
                        <li>
                            @if (! empty($item['url']))
                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            @else
                                <span class="store-footer__placeholder">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="store-footer__col">
                <p class="store-footer__heading">Legal</p>
                <ul class="store-footer__list" role="list">
                    @foreach ($themeLegalNav ?? [] as $item)
                        <li>
                            @if (! empty($item['url']))
                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            @else
                                <span class="store-footer__placeholder">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="store-footer__bottom">
        <p>&copy; {{ now()->year }} {{ $siteName ?? 'Store' }}. All rights reserved.</p>
    </div>
</footer>
