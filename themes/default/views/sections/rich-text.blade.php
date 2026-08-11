<section class="store-section store-richtext" aria-labelledby="richtext-heading">
    @if (! empty($section['title']))
        <h2 id="richtext-heading" class="store-section__title">{{ $section['title'] }}</h2>
    @endif
    @if (! empty($section['body']))
        <div class="store-richtext__body">
            <p>{{ $section['body'] }}</p>
        </div>
    @endif
</section>
