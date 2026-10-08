{{--
    Picks the merchant's invoice template. Unknown or removed values fall back to Classic.
    $invoiceTemplate may be passed by the Admin preview to show an unsaved choice.
--}}
@php
    $invoiceThemeConfig = app(\App\Agovena\Theme\ThemeManager::class)->config();
    $invoiceTemplates = $invoiceThemeConfig->schema()->field('invoices.template')?->options ?? ['classic'];
    $invoiceTemplate = isset($invoiceTemplate) && is_string($invoiceTemplate)
        ? $invoiceTemplate
        : $invoiceThemeConfig->string('invoices.template', 'classic');
    if (! in_array($invoiceTemplate, $invoiceTemplates, true)) {
        $invoiceTemplate = 'classic';
    }
    $invoiceAccent = $invoiceThemeConfig->string('colors.accent', '#155EEF');
    if (preg_match('/\A#[0-9a-fA-F]{6}\z/', $invoiceAccent) !== 1) {
        $invoiceAccent = '#155EEF';
    }
    $invoiceTemplateView = 'theme::invoices.templates.'.$invoiceTemplate;
@endphp
@include($invoiceTemplateView, ['document' => $document, 'printable' => $printable, 'accent' => $invoiceAccent])
