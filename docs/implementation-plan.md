# Implementierungsplan

## Aktueller Entwicklungsschritt

Grundlegende Projektstruktur und vertikaler Health-Slice.

## Freigegebene Architekturentscheidungen

- Clean Architecture in Domain, Application und Infrastructure.
- PHP 8.3+, Slim 4 als HTTP-Routing, Vue 3/TypeScript/Vite als statisches Frontend.
- MariaDB/PDO, PHPMailer und PHP-Sessions bleiben für spätere vertikale Schnitte.
- Kein Docker und keine permanente lokale Worker-Prozessstruktur.

## Freigegebener Umfang

- Projektmanifeste und lokale Startkonfiguration.
- `GET /api/health`.
- Contract-Definition und automatisierte Backend-/Frontend-Prüfung.

## Offene fachliche Fragen

- Kunden können laut Issue 1 nur einen Laufzeitdatensatz besitzen; ein Folgeverlauf mehrerer aufeinanderfolgender Abonnements ist fachlich offen.
- Die eindeutige Identität eines Kunden und die Datenbankfelder werden mit dem ersten CRUD-Slice festgelegt.
- Passwortverwaltung und Initialpasswort werden mit dem Authentisierungsschnitt festgelegt.

## Vorgeschlagene nächsten Schritte

1. PHP-Installation prüfen und vorhandene Abhängigkeiten installieren.
2. Kundenaggregat und CRUD-Use-Cases als nächsten vertikalen Slice implementieren.
3. Authentisierung, CSRF und Login-Ratenbegrenzung ergänzen.
