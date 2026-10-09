{{--
    Picks the template of the invoice design. $mode is `screen` (printable page), `preview` (Admin preview) or `pdf`.
--}}
@php
    $template = in_array($design->template, \App\Agovena\Invoices\InvoiceDesign::TEMPLATES, true)
        ? $design->template
        : \App\Agovena\Invoices\InvoiceDesign::DEFAULT_TEMPLATE;
    // Resolves to invoices/templates/{clean,business,bold,banner,angle}.blade.php.
    $templateView = 'invoices.templates.'.$template;
    $totalLabel = $document->isCreditNote || $document->isPaid() ? __('common.total') : __('invoices.total_due');
@endphp
@include($templateView, [
    'document' => $document,
    'design' => $design,
    'mode' => in_array($mode, ['screen', 'preview', 'pdf'], true) ? $mode : 'screen',
    'totalLabel' => $totalLabel,
])
