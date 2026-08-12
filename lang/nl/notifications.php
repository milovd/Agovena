<?php

return [
    'greeting' => 'Hallo :name,',
    'total' => 'Totaal: :total',
    'order_placed' => [
        'subject' => 'Bestelling :number ontvangen',
        'line' => 'We hebben je bestelling :number ontvangen.',
        'action' => 'Bestelling bekijken',
    ],
    'payment_recorded' => [
        'subject' => 'Betaling voor bestelling :number verwerkt',
        'line' => 'Je betaling voor bestelling :number is verwerkt.',
        'action' => 'Bestelling bekijken',
    ],
    'invoice_issued' => [
        'subject' => 'Factuur :number uitgegeven',
        'line' => 'Factuur :number is nu beschikbaar.',
        'action' => 'Factuur bekijken',
    ],
    'ticket_replied' => [
        'subject' => 'Antwoord op ticket :number',
        'line' => 'Een medewerker heeft gereageerd op “:subject”.',
        'action' => 'Antwoord bekijken',
    ],
    'subscription_cancelled' => [
        'subject' => 'Opzegging abonnement :number',
        'at_period_end' => 'Abonnement :number eindigt na de huidige periode.',
        'immediate' => 'Abonnement :number is opgezegd.',
        'action' => 'Abonnementen bekijken',
    ],
    'provisioning' => [
        'manual' => 'Handmatig',
        'refresh_status' => 'Status vernieuwen',
        'details' => 'Servicedetails',
        'status' => 'Status',
        'reference' => 'Referentie',
        'invalid_action' => 'Deze serviceactie is ongeldig.',
        'action_unavailable' => 'Deze serviceactie is niet beschikbaar.',
    ],
    'plan_changes' => [
        'not_allowed' => 'Deze planwijziging is niet toegestaan.',
        'currency_mismatch' => 'Plannen met verschillende valuta kunnen niet worden gewijzigd.',
        'order_line' => 'Planwijziging naar :product',
    ],
];
