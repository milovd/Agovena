<table class="invoice-doc__lines">
    <thead>
        <tr>
            <th scope="col">{{ __('invoices.item') }}</th>
            <th scope="col" class="invoice-doc__num">{{ __('invoices.qty') }}</th>
            <th scope="col" class="invoice-doc__num">{{ __('invoices.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>
                    {{ $line['label'] }}
                    @if ($line['options'] !== [])
                        <ul class="invoice-doc__options">
                            @foreach ($line['options'] as $option)
                                <li>{{ $option['label'] }}: {{ $option['value'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </td>
                <td class="invoice-doc__num">{{ $line['quantity'] }}</td>
                <td class="invoice-doc__num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
