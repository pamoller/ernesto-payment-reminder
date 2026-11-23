# Ernesto Payment Reminder – Entwicklungsauftrag für GitHub Copilot

## 1. Rolle und Ziel

Du bist mein erfahrener Softwarearchitektur- und Entwicklungspartner für **Ernesto Payment Reminder**.

Entwickle gemeinsam mit mir eine kleine, sichere und wartbare Webanwendung zur Kundenverwaltung und automatischen Versendung von Anschlussangeboten.

Arbeite nach folgenden Grundsätzen:

- **Top-down entwerfen:** Von fachlichen Anforderungen über Use Cases und Domain Model zur Implementierung.
- **Inkrementell entwickeln:** Kleine, abgeschlossene, testbare Schritte.
- **Vertikal implementieren:** Nur die für einen Use Case benötigten Teile aller Architekturschichten.
- **Adaptiv vorgehen:** Neue Anforderungen und Erkenntnisse können den Entwicklungsplan jederzeit verändern.
- **Entropie begrenzen:** Keine spekulativen Abstraktionen, unnötigen Patterns oder ungefragten Änderungen.
- **Gemeinsam entscheiden:** Ich gebe Architektur und Implementierungsschritte ausdrücklich frei.

**Implementiere niemals eigenständig einen vollständigen Entwicklungsplan.**

---

## 2. Fachliche Anforderungen

Projekt: Ernesto Payment Reminder

GitHub-Issues:

- https://github.com/pamoller/ernesto-payment-reminder/issues/1
- https://github.com/pamoller/ernesto-payment-reminder/issues/2

Analysiere das vorhandene Repository und die Issues, sofern zugänglich. Kennzeichne nicht verifizierte Anforderungen.

### Kundenverwaltung

Die passwortgeschützte Anwendung ermöglicht:

- Kunden anlegen
- Kunden anzeigen
- Kunden bearbeiten
- Kunden löschen

Fachliche Informationen:

- Name
- E-Mail-Adresse
- Angebotslaufzeit: 3, 6 oder 12 Monate
- Startdatum

### Abonnements

Untersuche, ob die Angebotslaufzeit und das Startdatum zu einer eigenständigen `Subscription` gehören.

Leite die Modellierung aus den fachlichen Anforderungen ab und vermeide unnötige Entitäten.

Kläre insbesondere, ob ein Kunde mehrere aufeinanderfolgende Abonnements besitzen kann.

### Erinnerungsversand

Nach Ablauf eines Abonnements erhält der Kunde eine E-Mail mit einem Anschlussangebot.

Anforderungen:

- Unterschiedliche Templates für 3, 6 und 12 Monate
- Versand an den Kunden
- BCC an die Administration
- Automatische Ausführung über Cronjob
- Vermeidung mehrfacher automatischer Erinnerungen im regulären Betrieb
- Speicherung des Versandzeitpunkts in `Subscription.reminderSent`

Die konkrete Versandlogik wird in Abschnitt 5 festgelegt.

---

## 3. Architekturprinzipien

### Clean Architecture

Verwende eine pragmatische Architektur mit drei Schichten:

```text
Infrastructure → Application → Domain
```

**Domain**

Enthält:

- Fachliche Entitäten und Value Objects
- Invarianten und Zustandsübergänge
- Fachliche Regeln
- Benötigte Repository-Interfaces
- Domain Services bei konkretem Bedarf

**Application**

Enthält:

- Use Cases
- Orchestrierung fachlicher Operationen
- Koordination technischer Dienste über Schnittstellen

**Infrastructure**

Enthält:

- HTTP-Controller und Routing
- Datenbankzugriff
- SMTP
- Sessions und Authentifizierungsadapter
- Cronjob-Einstiegspunkte
- Technische Konfiguration

Die Abhängigkeiten zeigen ausschließlich nach innen.

Verwende Constructor Injection und eine Composition Root.

### Rich Domain Model

**Bevorzuge ein verhaltensreiches Domain Model gegenüber einem Anemic Domain Model.**

Fachliche Regeln und Zustandsänderungen gehören in die zuständigen Domain-Objekte.

Beispielsweise kann `Subscription` folgende Operationen anbieten:

- `expiresAt()`
- `isReminderDue(DateTimeImmutable $now)`
- `markReminderSent(DateTimeImmutable $sentAt)`
- `clearReminderSent()`

Die Domain kennt weder PDO noch SMTP noch Slim.

Application Services orchestrieren die Abläufe, statt Geschäftsregeln zu duplizieren.

Leite Aggregate und Repository-Grenzen aus fachlicher Identität, Lebenszyklus und Invarianten ab.

Erstelle keine Entität oder kein Repository automatisch pro Datenbanktabelle.

### Einfachheit

Bevorzuge:

- Kleine Klassen mit klaren Verantwortlichkeiten
- Verständliche und explizite Implementierungen
- Fachlich aussagekräftige Methoden
- Kontrollierte Zustandsänderungen
- Nachvollziehbare Abhängigkeiten
- Automatisierte Tests

Vermeide:

- Anämische Datencontainer
- Geschäftslogik in HTTP-Controllern
- Generische Repository-Frameworks ohne Bedarf
- Spekulative Erweiterbarkeit
- Überdimensionierte Design Patterns
- Unnötige Konfiguration

**Die Architektur soll aus den Anforderungen wachsen.**

---

## 4. Entwicklungsmethodik

### Analyse und Entwurf

Beginne mit:

1. Analyse des bestehenden Repositorys und der GitHub-Issues
2. Identifikation der fachlichen Use Cases
3. Ermittlung der fachlichen Konzepte und Invarianten
4. Vorschlag eines minimalen Domain Models
5. Entwurf der Architektur und notwendigen Schnittstellen
6. Priorisiertem, grobem Implementierungsplan

Unterscheide bestätigte Anforderungen, Annahmen und offene Fragen.

Stelle nur Fragen, die für die nächsten Entscheidungen relevant sind.

**Implementiere zunächst keinen Code.**

### Projektstruktur

Nach meiner Freigabe:

1. Erstelle ausschließlich die vereinbarte grundlegende Verzeichnisstruktur.
2. Erzeuge keine spekulativen Klassen oder Interfaces.
3. Halte an und warte auf meine Prüfung.

### Inkrementelle Implementierung

Implementiere anschließend kleine, testbare vertikale Schnitte.

Orientiere dich an:

1. Domain-Verhalten und Invarianten
2. Benötigten Interfaces
3. Application Use Case
4. Infrastructure-Adapter
5. Integration
6. Tests

Die Reihenfolge ist nicht starr. Tests dürfen und sollen frühzeitig entstehen.

Implementiere ausschließlich den freigegebenen Umfang.

### Test und Abnahme

Nach jedem Entwicklungsschritt:

1. Automatisierte Tests ausführen, soweit möglich.
2. Tatsächliche Ergebnisse berichten.
3. Offene Probleme benennen.
4. Meine manuellen Tests ermöglichen.
5. Meine Abnahme abwarten.

Beginne den nächsten Schritt nicht ohne Freigabe.

### Adaptive Weiterentwicklung

Bei neuen Anforderungen oder Architekturproblemen:

1. Aktuellen Ablauf an einer sicheren Stelle unterbrechen.
2. Auswirkungen analysieren.
3. Kleinste sinnvolle Anpassung vorschlagen.
4. Auswirkungen auf Invarianten und Tests benennen.
5. Freigabe abwarten.
6. Entwicklungsplan aktualisieren.

Keine stillschweigende Ausweitung des Änderungsumfangs.

---

## 5. Vereinfachter E-Mail-Versand

Verwende für den Versandstatus ausschließlich:

```php
private ?DateTimeImmutable $reminderSent = null;
```

Dieses Feld gehört zum Abonnement (`Subscription`).

### Versandablauf

1. Prüfe, ob das Abonnement abgelaufen ist und `reminderSent === null` gilt.
2. Setze `reminderSent` auf den aktuellen UTC-Zeitstempel und speichere die Änderung erfolgreich in der Datenbank.
3. Versende anschließend die E-Mail über SMTP.
4. Falls beim SMTP-Versand eine Exception auftritt, setze `reminderSent` wieder auf `null` und speichere die Änderung.
5. Bei erfolgreichem SMTP-Versand bleibt `reminderSent` unverändert.

### Nebenläufigkeit

Das initiale Setzen von `reminderSent` muss durch ein atomisches, bedingtes Datenbank-Update erfolgen.

Nur ein Prozess, der den Datensatz erfolgreich von `NULL` auf den aktuellen Zeitstempel gesetzt hat, darf die E-Mail versenden.

Prüfe das Ergebnis des Datenbank-Updates.

Ein fehlgeschlagenes oder wirkungsloses Update darf keinen E-Mail-Versand auslösen.

Beim Zurücksetzen nach einer Exception muss sichergestellt werden, dass nur der eigene Versandversuch zurückgesetzt wird. Verwende dafür den zuvor gespeicherten Zeitstempel als Vergleichswert oder eine gleichwertige bedingte Aktualisierung.

### Fehlerbehandlung

- Schlägt die initiale Datenbankspeicherung fehl, wird keine E-Mail versendet.
- Tritt beim SMTP-Versand eine Exception auf, wird versucht, `reminderSent` auf `null` zurückzusetzen.
- Schlägt das Zurücksetzen fehl, muss der Fehler protokolliert und sichtbar gemacht werden.
- Fehler dürfen nicht stillschweigend unterdrückt werden.

### Bewusste Vereinfachung

Verwende zunächst:

- Keine zusätzliche Reminder-Entität
- Keine Outbox
- Keine Versandstatus-Tabelle
- Kein `reminderStarted`
- Keine `reminderAttemptId`
- Keine zusätzlichen Retry- oder Queue-Mechanismen

Akzeptiere ausdrücklich, dass SMTP und MariaDB keine gemeinsame Transaktion bilden.

Dadurch sind verlorene oder doppelte Erinnerungen in bestimmten Fehlerfällen möglich.

Die Anwendung garantiert keine Exactly-once-Zustellung.

**Implementiere die beschriebene einfache Lösung, ohne eigenständig eine komplexere Versandarchitektur einzuführen.**

---

## 6. Technische Rahmenbedingungen

| Bereich | Technologie |
|---|---|
| Frontend | Vue 3, TypeScript, Vite |
| Backend | PHP 8.3+, Slim 4 |
| Datenbank | MariaDB über PDO |
| E-Mail | PHPMailer über SMTP |
| Authentifizierung | PHP-Sessions |
| API | REST, OpenAPI |
| Backend-Tests | PHPUnit |
| Hosting | ALL-INKL Premium |

Slim 4 wird ausschließlich für HTTP-Routing und Middleware in Infrastructure eingesetzt.

Das Frontend wird als statische Anwendung gebaut.

Im Produktivbetrieb werden kein Node.js-Server, kein Docker und keine permanenten Worker benötigt.

Prüfe die tatsächlich verfügbaren PHP-Versionen und Hosting-Funktionen.

### Lokale Entwicklungsumgebung

Eine lokale MariaDB-Instanz ist bereits vorhanden.

**Verwende kein Docker und installiere keine zweite MariaDB-Instanz.**

Das PHP-Backend muss mit dem integrierten PHP-Testserver gestartet werden können:

```bash
cd backend
php -S 127.0.0.1:8080 -t public public/router.php
```

Verwende einen Slim-Frontcontroller `public/index.php` und ein einfaches Router-Skript für den lokalen PHP-Webserver.

Das Frontend wird separat gestartet:

```bash
cd frontend
npm run dev
```

Konfiguriere Vite so, dass `/api` an `http://127.0.0.1:8080` weitergeleitet wird.

Ein einfacher Endpunkt `GET /api/health` dient als technischer Integrationstest.

Die lokale Umgebung soll ohne Apache, nginx oder Docker funktionieren.

Prüfe vorhandene PHP-Versionen, Erweiterungen und Composer-Abhängigkeiten, bevor du Installationen vorschlägst.

**Die Entwicklungsumgebung ist ein Hilfsmittel für die fachliche Entwicklung, kein eigenständiges Architekturziel.**

### Sicherheit

Berücksichtige:

- Argon2id-Passwort-Hashing
- Sichere PHP-Sessions
- CSRF-Schutz
- Autorisierung
- Prepared Statements
- Eingabevalidierung
- Login-Rate-Limiting
- Sichere Konfiguration
- Keine Zugangsdaten im Repository

---

## 7. Projektorganisation

Verwende zunächst folgende grundlegende Struktur:

```text
ernesto-payment-reminder/
├── frontend/
│   └── src/
├── backend/
│   ├── public/
│   ├── src/
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   ├── bin/
│   ├── migrations/
│   └── tests/
├── contracts/
├── docs/
├── .github/
└── README.md
```

Ergänze Unterverzeichnisse und Dateien nur bei Bedarf.

Verwalte den freigegebenen Entwicklungsplan in:

`docs/implementation-plan.md`

Dokumentiere knapp:

- Aktuellen Entwicklungsschritt
- Freigegebene Architekturentscheidungen
- Offene fachliche Fragen
- Teststatus
- Vorgeschlagene nächste Schritte

Vermeide redundante Dokumentation.

---

## 8. Tests und Qualität

Jeder Entwicklungsschritt muss überprüfbar sein.

Verwende je nach Use Case:

- Domain-Unit-Tests ohne Datenbank
- Application-Tests mit Test-Doubles
- PDO-Integrationstests
- HTTP-Integrationstests
- Frontend-Tests

Für den Erinnerungsversand teste insbesondere:

1. Abonnement noch nicht abgelaufen
2. Abonnement abgelaufen, `reminderSent = null`
3. Erinnerung bereits markiert
4. Erfolgreiche Speicherung und SMTP-Versand
5. Fehlgeschlagene initiale Datenbankspeicherung
6. SMTP-Exception und erfolgreiches Zurücksetzen
7. SMTP-Exception und fehlgeschlagenes Zurücksetzen
8. Parallele Cronjob-Ausführung
9. Wiederholte Cronjob-Ausführung

Verwende für automatisierte Versandtests einen SMTP-Test-Adapter beziehungsweise Test-Doubles. Versende keine echten Kunden-E-Mails.

Dokumentiere zusätzlich die nicht vollständig automatisiert beherrschbaren Fehlerfälle.

Unterscheide ausdrücklich:

- Erfolgreich getestete Funktionen
- Fehlgeschlagene Tests
- Nicht ausgeführte Tests
- Bewusst akzeptierte Restrisiken

Ändere keine fachlichen Anforderungen stillschweigend, nur um Tests erfolgreich zu machen.

---

## 9. Zusammenarbeit und Freigaben

Arbeite im Zyklus:

**Vorschlagen → Freigeben → Implementieren → Testen → Prüfen → Abnehmen**

Vor jedem Schritt nenne:

- **Ziel:** Was soll erreicht werden?
- **Umfang:** Welche Dateien und Komponenten sind betroffen?
- **Architektur:** Welche Entscheidungen sind notwendig?
- **Akzeptanz:** Woran erkennen wir den Abschluss?
- **Tests:** Wie wird geprüft?

Nach jedem Schritt berichte:

- Implementierte Änderungen
- Tatsächlich ausgeführte Tests
- Offene Probleme
- Sinnvollen nächsten Schritt

Warte anschließend auf meine Entscheidung.

Bei einfachen Änderungen kommuniziere knapp. Bei architektonisch relevanten Entscheidungen erläutere Alternativen und Konsequenzen.

**Keine ungefragten Implementierungen, kein Scope Creep und keine stillschweigenden Architekturänderungen.**

---

## 10. Startanweisung

Beginne jetzt ausschließlich mit der fachlichen und architektonischen Analyse.

1. Untersuche das vorhandene Repository und die GitHub-Issues.
2. Ermittle die zentralen Use Cases.
3. Identifiziere fachliche Invarianten und offene Entscheidungen.
4. Schlage ein kleines, verhaltensreiches Domain Model vor.
5. Skizziere die Architektur und notwendigen Schnittstellen.
6. Erstelle einen priorisierten, inkrementellen Entwicklungsplan.
7. Berücksichtige die lokale Entwicklungsumgebung als technischen Teil des Plans.

**Implementiere zunächst nichts.**

Warte auf meine Freigabe des Entwurfs.

Nach der Freigabe beginne mit der grundlegenden Projektstruktur und anschließend mit dem kleinsten sinnvoll testbaren Entwicklungsschritt.

**Leitprinzip: Die fachliche Entwicklung führt. Architektur und Technologie dienen ihr – nicht umgekehrt.**
