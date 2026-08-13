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
    'credit_note_issued' => [
        'subject' => 'Creditnota :number uitgegeven',
        'line' => 'Creditnota :number is nu beschikbaar.',
        'action' => 'Creditnota bekijken',
    ],
    'refund_processed' => [
        'subject' => 'Terugbetaling verwerkt voor bestelling :number',
        'line' => 'Een terugbetaling van :total is verwerkt voor bestelling :number.',
        'action' => 'Bestelling bekijken',
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
    'shipment_sent' => [
        'subject' => 'Bestelling :number is verzonden',
        'line' => 'Er is een zending onderweg voor bestelling :number.',
        'action' => 'Bestelling bekijken',
    ],
    'subscription_renewal' => [
        'subject' => 'Verlengingsfactuur voor :number',
        'line' => 'Verlengingsbestelling :detail is klaar voor abonnement :number.',
        'action' => 'Bestelling bekijken',
    ],
    'subscription_past_due' => [
        'subject' => 'Abonnement :number is achterstallig',
        'line' => 'De betaling voor abonnement :number is te laat.',
        'action' => 'Abonnementen bekijken',
    ],
    'plan_change_applied' => [
        'subject' => 'Planwijziging toegepast',
        'line' => 'Je planwijziging voor :number is nu actief.',
        'action' => 'Abonnementen bekijken',
    ],
    'service_activated' => [
        'subject' => 'Dienst :number is actief',
        'line' => 'Dienst :number is geactiveerd.',
        'action' => 'Dienst bekijken',
    ],
    'service_suspended' => [
        'subject' => 'Dienst :number is gepauzeerd',
        'line' => 'Dienst :number is gepauzeerd.',
        'action' => 'Dienst bekijken',
    ],
    'digital_entitlement_granted' => [
        'subject' => 'Je download is klaar',
        'line' => 'Downloads voor bestelling :number zijn nu beschikbaar.',
        'action' => 'Downloads bekijken',
    ],
    'event_ticket_issued' => [
        'subject' => 'Je tickets voor bestelling :number',
        'line' => 'Evenementtickets voor bestelling :number zijn nu beschikbaar.',
        'action' => 'Tickets bekijken',
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
        'already_pending' => 'Er staat al een planwijziging open voor dit abonnement.',
        'cannot_apply' => 'Deze planwijziging kan niet worden toegepast.',
        'cannot_cancel' => 'Deze planwijziging kan niet worden geannuleerd.',
    ],
];
