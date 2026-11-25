# Implementierungsplan

## Aktueller Entwicklungsschritt
Grundlegende Projektstruktur, Kunden-CRUD, Erinnerungsversand und Authentisierung-Slice.

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
- Erinnerungsmarkierung erfolgt atomisch über `subscriptions.reminder_sent`.
- SMTP-Ausnahme setzt die eigene atomare Markierung bedingt zurück.
- Der manuelle Kundenlisten-Knopf darf vor Ablauf und wiederholt versenden; bei Erfolg wird `reminder_sent` aktualisiert.
- Kunden- und Mutationsrouten sind durch PHP-Sessions und CSRF-Tokens geschützt.
- Der Administratorzugang wird über `AUTH_USERNAME` und einen Argon2id-Hash in `AUTH_PASSWORD_HASH` konfiguriert.

## Offene fachliche Fragen

- Ein Kunde besitzt zunächst genau ein Abonnement. Mehrere aufeinanderfolgende Abonnements bleiben offen.
- Die eindeutige Identität eines Kunden und die Datenbankfelder werden mit dem ersten CRUD-Slice festgelegt.
- Passwortverwaltung und Initialpasswort werden mit dem Authentisierungsschnitt festgelegt.

## Teststatus

- Backend-Domain- und PDO-Repositorytests: 5 Tests, 16 Assertions erfolgreich.
- Erinnerungs-Slice: 14 Backend-Tests, 44 Assertions erfolgreich.
- Frontend-Vue-Test: 1 Test erfolgreich.
- Frontend-Produktionsbuild erfolgreich.
- Laufzeitvertrag `api-test.sh`: Health sowie Kunden-CRUD erfolgreich.
- SQLite-Migration und `bin/run-reminders.php`: erfolgreicher Leerlauf ohne Kunden.
- PHP-Session-Login und geschützte Kundenrouten: Laufzeitvertrag erfolgreich.
- Manueller Versand: Service- und Repositorytests erfolgreich; echte SMTP-Zustellung bleibt konfigurationsabhängig.

## Vorgeschlagene nächsten Schritte

1. PHP-Installation prüfen und vorhandene Abhängigkeiten installieren.
2. Kundenaggregat und CRUD-Use-Cases wurden als vertikaler Slice implementiert.
3. SMTP-Zugangsdaten in der Zielumgebung konfigurieren und die Zustellung mit einer Testadresse manuell abnehmen.
