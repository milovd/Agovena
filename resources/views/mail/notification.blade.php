@component('mail::message')
{!! $bodyHtml !!}

@if (filled($actionUrl))
@component('mail::button', ['url' => $actionUrl])
{{ $actionLabel }}
@endcomponent
@endif
@endcomponent
