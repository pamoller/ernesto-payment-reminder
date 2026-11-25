# Implementierungsplan

## Aktueller Entwicklungsschritt

Grundlegende Projektstruktur und vollständiger Kunden-CRUD-Slice.

## Freigegebene Architekturentscheidungen

- Clean Architecture in Domain, Application und Infrastructure.
- PHP 8.3+, Slim 4 als HTTP-Routing, Vue 3/TypeScript/Vite als statisches Frontend.
- MariaDB/PDO, PHPMailer und PHP-Sessions bleiben für spätere vertikale Schnitte.
- Kein Docker und keine permanente lokale Worker-Prozessstruktur.
- Kunden sind derzeit mit genau einem Abonnement modelliert; dieses Modell kann später um mehrere aufeinanderfolgende Abonnements erweitert werden.

## Freigegebener Umfang

- Projektmanifeste und lokale Startkonfiguration.
- `GET /api/health`.
- Kunden-CRUD über REST und Vue-Oberfläche.
- Laufzeit 3, 6 oder 12 Monate und Startdatum pro Abonnement.
- MariaDB-kompatible PDO-Persistenz und lokaler SQLite-Fallback.

## Offene fachliche Fragen

- Ein Kunde besitzt zunächst genau ein Abonnement. Mehrere aufeinanderfolgende Abonnements bleiben offen.
- Die eindeutige Identität eines Kunden und die Datenbankfelder werden mit dem ersten CRUD-Slice festgelegt.
- Passwortverwaltung und Initialpasswort werden mit dem Authentisierungsschnitt festgelegt.

## Teststatus

- Backend-Domain- und PDO-Repositorytests: 5 Tests, 16 Assertions erfolgreich.
- Frontend-Vue-Test: 1 Test erfolgreich.
- Frontend-Produktionsbuild erfolgreich.
- Laufzeitvertrag `api-test.sh`: Health sowie Kunden-CRUD erfolgreich.

## Vorgeschlagene nächsten Schritte

1. PHP-Installation prüfen und vorhandene Abhängigkeiten installieren.
2. Kundenaggregat und CRUD-Use-Cases wurden als vertikaler Slice implementiert.
3. Authentisierung, CSRF und Login-Ratenbegrenzung ergänzen.
