<?php

declare(strict_types=1);

return [
    'print' => 'Afdrukken',
    'seller' => 'Van',
    'bill_to' => 'Factuuradres',
    'issued' => 'Factuurdatum',
    'paid_on' => 'Betaald op',
    'due_on' => 'Vervaldatum',
    'seller_vat_number' => 'Btw-nummer',
    'seller_company_number' => 'Ondernemingsnummer',
    'unit_price' => 'Prijs per stuk',
    'tax_included' => ':label (inbegrepen)',
    'item' => 'Artikel',
    'qty' => 'Aantal',
    'amount' => 'Bedrag',
    'status' => [
        'issued' => 'Uitgegeven',
        'paid' => 'Betaald',
        'void' => 'Vervallen',
    ],

    // Factuursjablonen: documentkoppen.
    'document_title' => 'Factuur',
    'status_label' => 'Status',
    'details' => 'Gegevens',

    // Voorbeeldfactuur voor de sjabloonweergave in Admin.
    'preview' => [
        'customer' => 'Voorbeeldklant',
        'company' => 'Voorbeeldbedrijf B.V.',
        'street' => 'Voorbeeldstraat 1',
        'city' => 'Amsterdam',
        'vat_label' => 'Btw-nummer',
        'tax_name' => 'Btw 21%',
        'item' => 'Voorbeeldproduct',
        'option_label' => 'Maat',
        'option_value' => 'Groot',
        'service' => 'Installatieservice',
        'discount' => 'Welkomstkorting',
    ],
];
