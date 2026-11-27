# Ernesto Payment Reminder

Ernesto Payment Reminder verwaltet Kunden und erinnert sie nach Ablauf eines Abonnements per E-Mail an Anschlussangebote.

## Lokale Entwicklung

Backend:

```bash
cd backend
composer install
composer start
```

Frontend:

```bash
cd frontend
npm install
npm run dev
```

Die Vite-Anwendung leitet `/api` an `http://127.0.0.1:8080` weiter.

## Erinnerungsversand

SMTP-Zugangsdaten in `backend/.env` beziehungsweise in der Produktionsumgebung konfigurieren. Die verfügbaren Variablen stehen in `backend/.env.example`.

```bash
cd backend
php bin/migrate.php
php bin/run-reminders.php
```

Der Cronjob darf den Vorgang wiederholt ausführen. `subscriptions.reminder_sent` enthält den letzten erfolgreichen Versand für den aktuellen Subscription-Status. Bei einem tatsächlichen Statuswechsel wird der Wert zurückgesetzt; ein gleichbleibender Status ändert weder Statuszeitpunkt noch Versandzeitpunkt.

Vor einem automatischen Versand setzt der Cronjob `reminder_claimed_at` atomar und zustandsgebunden. Nach erfolgreichem SMTP-Versand wird `reminder_sent` gesetzt und der Claim entfernt. Bei einer SMTP-Ausnahme wird der Claim sofort freigegeben. Ein Claim, der älter als 60 Minuten ist, darf vom nächsten Lauf übernommen werden; dadurch blockiert ein abgestürzter Prozess einen Versand nicht dauerhaft.

Automatische Erinnerungen werden höchstens bis einschließlich `starts_at + Laufzeit + MESSAGE_LATEST_SEND` Tage versendet. `MESSAGE_LATEST_SEND` ist eine nicht-negative Ganzzahl in Tagen. Verspätete automatische Nachrichten werden als `latestSendSkipped` im Ergebnis des Cronjobs ausgewiesen; manuelle Status-Mails bleiben davon unberührt.

In der Abonnementdetailansicht kann der Status im Bearbeitungsformular gewählt und gespeichert werden. Jeder Speichervorgang setzt `subscription_status_changed` auf den aktuellen Zeitpunkt und `reminder_sent` auf `NULL`. Über **Erinnern** direkt neben dem Statusfeld kann die E-Mail des gespeicherten aktuellen Status erneut versendet werden. Ein fehlgeschlagener E-Mail-Versand bleibt vom erfolgreich persistierten Statuswechsel getrennt und wird separat im Ergebnis gemeldet.

E-Mail-Betreff und vollständiger Nachrichtentext kommen aus `subscription_status`. Unterstützte Platzhalter sind unter anderem `{{customer_name}}`, `{{customer_email}}`, `{{duration}}`, `{{duration_months}}`, `{{start_date}}`, `{{expires_at}}`, `{{subscription_status}}` und `{{subscription_status_changed}}`. Unbekannte Platzhalter führen zu einem Versandfehler.

## Automatischer Statusablauf

Der nächtliche Lauf führt pro Abonnement und Durchlauf höchstens einen Übergang aus:

`Bezahlt → Fällig → Säumig → Beendet`

Der nächste Übergang wird aus der Duration des Zielstatus und entweder dem Ablaufdatum oder dem letzten Statuswechsel berechnet. Ein persistierter Zustandswechsel wird auch bei einem späteren SMTP-Fehler nicht zurückgenommen. Fehlt die E-Mail eines bereits erreichten Status, versucht ein späterer Lauf sie erneut zu versenden. `Beendet` besitzt keinen automatischen Folgeübergang.

Die Vue-Oberfläche verwendet Vue Router und Tailwind CSS. Geschützte Routen starten standardmäßig unter `/subscriptions`; `/subscriptions/new` erstellt ein neues Abonnement und `/subscriptions/:id` öffnet die Detailansicht.

## Authentifizierung

Der Administratorzugang wird ausschließlich über Umgebungsvariablen konfiguriert:

```bash
export AUTH_USERNAME='admin'
AUTH_PASSWORD_HASH="$(php -r 'echo password_hash("Ihr-sicheres-Passwort", PASSWORD_ARGON2ID);')"
export AUTH_PASSWORD_HASH
```

Das Hash wird in `AUTH_PASSWORD_HASH` gesetzt. Das Klartextpasswort wird weder im Repository noch in der Anwendung gespeichert.

## Datenbank-Bootstrap

Mit einem MariaDB-Administrationszugang werden die Datenbank und beide Anwendungskonten angelegt:

```bash
cd backend
composer db:bootstrap
```

Die Werte `DB_PASSWORD`, `DB_ADMIN_USER`, `DB_ADMIN_PASSWORD`, `BOOTSTRAP_USER` und `BOOTSTRAP_PASSWORD` werden aus `backend/.env` oder der Prozessumgebung gelesen. Die Anwendung verwendet `ernesto` mit nur `SELECT`, `INSERT`, `UPDATE` und `DELETE`; `ernesto_admin` führt Schema-Migrationen aus und erhält dafür Datenbankverwaltungsrechte.

## Tests

Task targets for local development:

```bash
task db:init
task start
task backend:start
task frontend:start
task remind
task test
```

`task db:reset` deletes the configured database. It requires the exact database name as confirmation, for example `CONFIRM_DB_RESET=ernesto_subscriptions task db:reset`.

```bash
cd backend
composer test
```

```bash
cd frontend
npm test
npm run build
```
