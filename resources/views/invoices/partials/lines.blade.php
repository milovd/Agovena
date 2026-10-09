{{-- Line items. $numbered adds a running number column. --}}
@php
    $numbered = $numbered ?? false;
@endphp
<table class="inv-lines">
    <thead>
        <tr>
            @if ($numbered)
                <th scope="col" class="inv-lines__no">{{ __('invoices.line_number') }}</th>
            @endif
            <th scope="col" class="inv-lines__item">{{ __('invoices.description') }}</th>
            <th scope="col" class="inv-num">{{ __('invoices.qty') }}</th>
            <th scope="col" class="inv-num">{{ __('invoices.unit_price') }}</th>
            <th scope="col" class="inv-num">{{ __('invoices.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->lines as $line)
            <tr>
                @if ($numbered)
                    <td class="inv-lines__no">{{ $loop->iteration }}</td>
                @endif
                <td class="inv-lines__item">
                    {{ $line['label'] }}
                    @if ($line['options'] !== [])
                        <ul class="inv-options">
                            @foreach ($line['options'] as $option)
                                <li>{{ $option['label'] }}: {{ $option['value'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </td>
                <td class="inv-num">{{ $line['quantity'] }}</td>
                <td class="inv-num">{{ $line['unitAmount'] }}</td>
                <td class="inv-num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
