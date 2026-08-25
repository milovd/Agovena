# Auditlog

De auditlog is het operationele en securityspoor van Agovena. Het doel is onderzoek mogelijk maken, niet debuglogging vervangen.

## Wat een regel bevat

Nieuwe regels bevatten:

- unieke `event_id`;
- tijdstip;
- actor type en actor ID voor medewerker, klant of systeem;
- actie en categorie;
- ernstniveau: `info`, `warning` of `critical`;
- resultaat: `success`, `failure`, `denied` of `pending`;
- polymorf objecttype en object-ID;
- eventgegevens;
- optionele before- en after-snapshot;
- request-ID en correlation-ID;
- HTTP route, methode, statuscode, IP en user-agent wanneer beschikbaar;
- technische context zoals bron, route, methode en pad;
- integrity hash voor nieuwe regels.

De applicatie heeft momenteel geen tenantmodel. Tenantcontext mag daarom niet worden verzonnen. Wanneer multi-tenancy wordt toegevoegd, moet `tenant_id` expliciet aan de auditcontext en queryscoping worden toegevoegd.

## Onderzoek in de Admin UI

Ga naar **Admin > Auditlogboek**. Gebruik eerst:

1. `Request-ID` voor één HTTP-request;
2. `Correlation-ID` voor een checkout, webhook, job of supportonderzoek over meerdere requests;
3. objecttype en object-ID voor een order, payment, refund, klant of ticket;
4. actie, categorie, resultaat en ernst om ruis te beperken;
5. de detailweergave voor properties, before, after en technische context;
6. CSV-export voor offline analyse of een supportdossier.

De zoekbalk zoekt ook in event-ID, actor- en object-ID, request- en correlation-ID, IP en geredigeerde eventgegevens.

## Redaction

Auditgegevens mogen nooit secrets worden. De writer redigeert geneste waarden met onder meer deze sleuteltypen:

- wachtwoorden, tokens, API-keys, authorization headers, cookies en private keys;
- webhook- en signing secrets;
- connection strings en credentials;
- betaalkaartnummer, PAN, CVV, CVC en OTP;
- e-mail, telefoon, adres en persoonsnaam wanneer zij als event property worden aangeleverd.

Bearer tokens, PEM private keys en herkenbare provider-keypatronen worden ook op inhoud herkend. De applicatie bewaart voor betalingen wel bruikbare niet-geheime identifiers zoals lokale payment-ID, order-ID, valuta, bedrag en providerreferentie.

Bestaande auditregels van vóór de uitgebreide auditmigration kunnen geen integrity hash hebben. De UI toont die status expliciet als niet beschikbaar. Retention verwijdert verlopen regels via `agovena:prune-logs`; auditregels zijn via het model append-only.

## Eventcategorieën

- `auth` en `security`: login, MFA, tokens, verificatie en toegangsbeslissingen;
- `commerce`: orders, invoices, credit notes, inventory en checkout;
- `payment` en `refund`: payment attempts, providerstatus, refunds en financiële afwijkingen;
- `webhook`: inkomende en uitgaande webhookverwerking;
- `support`: tickets, interne notities en supportmutaties;
- `privacy`: klantanonymisatie en privacyacties;
- `notification`: notification- en mailacties;
- `admin` en `system`: beheer-, installatie-, package- en operationele acties.

Nieuwe gevoelige flows horen `AuditLogger::logChange()` te gebruiken wanneer een before/after-vergelijking nodig is. Mislukte, geweigerde en asynchrone acties horen hun resultaat expliciet als `failure`, `denied` of `pending` te registreren.

## Releasegrens

De auditloglaag is gebouwd en bruikbaar voor onderzoek. Dit betekent niet dat iedere mogelijke read-only page view automatisch wordt gelogd. Voor releasecontrole moet elke muterende security-, admin-, privacy-, financiële-, voorraad-, entitlement- en providerflow een expliciete auditactie hebben. Providercredentials, betaalkaartgegevens en volledige persoonsgegevens blijven buiten de auditlog.
