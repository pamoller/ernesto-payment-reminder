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
