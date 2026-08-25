# Geconsolideerde recurring billing

Agovena kan renewalfacturen voor meerdere actieve services van dezelfde klant samenvoegen in één renewal order, één payment en één invoice. Dit is core billinggedrag. De Subscriptions-module levert de recurring sources aan de core billing service.

## Gedrag

- Services worden gegroepeerd op klant, e-mail, valuta, payment gateway, interval en interval count.
- Een groep wordt verwerkt zodra minstens één service due is.
- Services met een due date binnen het consolidatievenster worden meegenomen.
- De vroegste due date wordt de gemeenschappelijke order- en factuurdue date.
- Een service die later due was krijgt een prijs die proportioneel wordt verminderd met de al betaalde dagen.
- Proratie werkt uitsluitend met integer minor units en half-up afronding.
- Eén order bevat meerdere order items en één payment.
- `IssueInvoiceFromOrder` maakt daar één invoice van en neemt de orderdue date over.
- Na betaling worden alle pending renewal-records van die order atomisch als betaald verwerkt en krijgen alle subscriptions dezelfde aligned periode.

## Consolidatievenster

De standaardwaarde is 31 dagen. Dit voorkomt dat services met een veel later billingmoment ongewenst in een lopende factuur terechtkomen.

De instelling is:

```text
store.subscription_consolidation_window_days
```

De effectieve waarde wordt server-side begrensd op 1 tot en met 31 dagen.

## Financiële veiligheid

- Ordercreatie gebruikt een deterministische idempotency key met alle recurring source IDs en due dates.
- Concurrente scheduler-runs kunnen daardoor geen tweede geconsolideerde order voor dezelfde batch maken.
- Klanten, valuta en gateway worden niet gemengd in één payment boundary.
- Geen floating-point berekeningen worden gebruikt voor bedragen.
- Als reeds betaalde dagen de volledige periode dekken, wordt de betreffende line total nul.
- De broncontext voor elk item wordt in `options_snapshot.consolidated_billing` opgeslagen voor invoice- en supportonderzoek.
- De auditlog registreert `billing.renewal_consolidated` zonder secrets of betaalkaartgegevens.

## Relevante code

Core:

- `app/Agovena/Billing/ConsolidatedBillingLine.php`
- `app/Agovena/Billing/ConsolidatedRenewalOrderBuilder.php`
- `database/migrations/2026_08_25_190000_add_due_at_to_orders.php`

Integratie:

- `optional-packages/modules/subscriptions/src/SubscriptionService.php`

Verificatie:

- `tests/Unit/ConsolidatedBillingLineTest.php`
- `tests/Feature/SubscriptionRenewalTest.php`

## Bewuste grenzen

- Consolidatie werkt momenteel voor de Subscriptions-module. Andere recurring sources kunnen dezelfde core DTO en builder gebruiken.
- Services met verschillende valuta, gateways, intervallen of interval counts blijven aparte billing batches.
- Echte providerautocharge blijft afhankelijk van de betreffende gateway en herbruikbare authorization.
- De bestaande subscription retry- en pending-state blijft leidend. Consolidatie verandert de payment boundary, niet de providerstatusregels.
