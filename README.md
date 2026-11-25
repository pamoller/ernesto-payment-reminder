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

Der Cronjob darf den Vorgang wiederholt ausführen. Bereits versendete Erinnerungen werden über `subscriptions.reminder_sent` nicht erneut ausgelöst.

## Tests

```bash
cd backend
composer test
```

```bash
cd frontend
npm test
npm run build
```
