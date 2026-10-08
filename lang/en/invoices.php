<?php

declare(strict_types=1);

return [
    'print' => 'Print',
    'seller' => 'From',
    'bill_to' => 'Bill to',
    'issued' => 'Issue date',
    'paid_on' => 'Paid on',
    'item' => 'Item',
    'qty' => 'Qty',
    'amount' => 'Amount',
    'status' => [
        'issued' => 'Issued',
        'paid' => 'Paid',
        'void' => 'Void',
    ],

    // Invoice templates: document headings.
    'document_title' => 'Invoice',
    'status_label' => 'Status',
    'details' => 'Details',

    // Sample invoice for the template preview in Admin.
    'preview' => [
        'customer' => 'Sample Customer',
        'company' => 'Sample Company Ltd',
        'street' => 'Example Street 1',
        'city' => 'Amsterdam',
        'vat_label' => 'VAT number',
        'tax_name' => 'VAT 21%',
        'item' => 'Sample product',
        'option_label' => 'Size',
        'option_value' => 'Large',
        'service' => 'Installation service',
        'discount' => 'Welcome discount',
    ],
];
