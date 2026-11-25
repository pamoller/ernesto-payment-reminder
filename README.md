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

## Authentifizierung

Der Administratorzugang wird ausschließlich über Umgebungsvariablen konfiguriert:

```bash
export AUTH_USERNAME='admin'
AUTH_PASSWORD_HASH="$(php -r 'echo password_hash("Ihr-sicheres-Passwort", PASSWORD_ARGON2ID);')"
export AUTH_PASSWORD_HASH
```

Das Hash wird in `AUTH_PASSWORD_HASH` gesetzt. Das Klartextpasswort wird weder im Repository noch in der Anwendung gespeichert.

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
