# Erweiterung des Payment Reminders um eine Subscription State Machine

Erweitere die bestehende Payment-Reminder-Anwendung um eine zustandsbasierte Abonnementverwaltung mit konfigurierbaren Zeitintervallen und zustandsbezogenen E-Mail-Benachrichtigungen.

Analysiere zunächst die bestehende Implementierung. Behalte die Architektur, den Entwicklungsstil, die Namenskonventionen und das visuelle Design bei.

Verwende bestehende Komponenten und Funktionalitäten wieder. Vermeide unnötige Abstraktionen, zusätzliche Abhängigkeiten und fachfremde Refactorings.

## 1. Entwicklungsstrategie: Vertical Slices

Arbeite die Funktionserweiterung schrittweise in fachlich zusammenhängenden Vertical Slices ab.

Ein Vertical Slice implementiert eine vollständige, in sich geschlossene und überprüfbare Funktionserweiterung über alle betroffenen Architekturschichten hinweg:

- Domain
- Application
- Infrastructure
- REST API
- Frontend

Erweitere in jedem Entwicklungsschritt die jeweils betroffenen Schichten und ergänze die entsprechenden automatisierten Tests.

Implementiere nicht zunächst sämtliche Änderungen am Domain Model, anschließend sämtliche Application Services und danach die Infrastructure. Entwickle stattdessen fachliche Funktionen durchgängig über die Architekturschichten hinweg.

### Ablauf eines Entwicklungsschritts

1. Beschreibe das fachliche Ziel des Vertical Slice.
2. Identifiziere die betroffenen Komponenten.
3. Implementiere die Funktionserweiterung einschließlich der erforderlichen Datenbankänderungen.
4. Ergänze oder aktualisiere die entsprechenden Tests.
5. Führe sämtliche bestehenden und neuen automatisierten Tests aus.
6. Behebe aufgetretene Fehler und Regressionen.
7. Beschreibe die implementierte Funktion, die Änderungen und die Testergebnisse.
8. Stelle eine kurze Anleitung für die manuellen Smoke Tests bereit.
9. Halte an und warte auf meine ausdrückliche Freigabe.

Beginne den nächsten Vertical Slice erst nach meiner Freigabe.

Die Anwendung muss nach jedem abgeschlossenen Entwicklungsschritt funktionsfähig und manuell überprüfbar sein.

Analysiere zu Beginn die bestehende Implementierung und schlage eine geeignete Aufteilung in Vertical Slices vor. Warte auf meine Zustimmung zum Entwicklungsplan, bevor du mit der Implementierung beginnst.

## 2. Datenmodell

### Tabelle `subscription_status`

Führe eine neue Tabelle mit folgenden Attributen ein:

- `id`: INT, Primärschlüssel
- `name`: VARCHAR, NOT NULL
- `duration`: VARCHAR, nullable
- `subject`: VARCHAR, nullable
- `message`: TEXT, nullable

Initialisiere die Tabelle mit folgenden Datensätzen:

| id | name | duration | subject | message |
|---|---|---|---|---|
| 1 | Bezahlt | NULL | Zahlungsbestätigung | Danke für das Abo! |
| 2 | Fällig | 0 DAYS | Zahlungserinnerung | Bestehender Zahlungserinnerungstext |
| 3 | Säumig | 7 DAYS | Mahnung | Mahnungstext |
| 4 | Beendet | 7 DAYS | Beendigung des Abonnements | Beendigungstext |

Das Feld `subject` enthält den vollständigen E-Mail-Betreff als einfachen String.

Das Feld `message` enthält den vollständigen E-Mail-Text als Template String einschließlich aller Platzhalter.

Übernimm den vollständigen Inhalt des bestehenden Zahlungserinnerungs-Templates in das Feld `message` des Zustands 2.

Erstelle für die Zustände 3 und 4 geeignete deutschsprachige Nachrichtentexte im Stil der bestehenden Zahlungserinnerung und speichere diese vollständig im jeweiligen Feld `message`.

Speichere keine Template-IDs, Dateinamen, Dateipfade oder Referenzen auf externe Templates.

### Tabelle `subscription`

Erweitere die bestehende Tabelle um:

- `subscription_status_id`: Fremdschlüssel auf `subscription_status.id`
- `subscription_status_changed`: DATETIME, Zeitpunkt des letzten tatsächlichen Zustandswechsels

Erhalte bei der Datenbankmigration sämtliche bestehenden Abonnementdaten.

Kläre die initiale Statuszuweisung bestehender Abonnements vor der Migration.

Versende während der Migration keine E-Mails.

## 3. Subscription State Machine

Integriere eine Finite State Machine in das bestehende Domain Model.

Der reguläre Lebenszyklus lautet:

```text
Bezahlt (1)
    |
    v
Fällig (2)
    |
    v
Säumig (3)
    |
    v
Beendet (4)
```

Erlaubte reguläre Zustandsübergänge:

| Von | Nach | Auslöser |
|---|---|---|
| 1 | 2 | Fälligkeit erreicht |
| 2 | 3 | Duration abgelaufen |
| 3 | 4 | Duration abgelaufen |
| 2 | 1 | Zahlung registriert |
| 3 | 1 | Zahlung registriert |
| 4 | 1 | Reaktivierung |

Alle anderen regulären Zustandsübergänge sind unzulässig.

Der Zustand `Beendet` besitzt keinen automatischen ausgehenden Zustandsübergang.

Der Administrator darf unabhängig von den regulären Übergangsregeln jeden gültigen Zustand setzen.

Aktualisiere bei jedem tatsächlichen Zustandswechsel `subscription_status_changed` mit dem aktuellen Zeitstempel.

Das erneute Setzen desselben Zustands verändert weder den Zeitstempel noch löst es eine E-Mail aus.

## 4. Duration

Das Attribut `duration` beschreibt ein relatives Zeitintervall als String.

Beispiele:

- `0 DAYS`
- `7 DAYS`
- `2 WEEKS`
- `8 MONTH`
- `1 YEAR`

Unterstütze DAY, WEEK, MONTH und YEAR im Singular und Plural sowie nicht negative ganzzahlige Werte.

Verwende kalenderbasierte Datumsarithmetik.

Existiert bei der Addition von Monaten oder Jahren der entsprechende Kalendertag im Zielmonat nicht, verwende den letzten gültigen Tag des Zielmonats.

Kapsle das Parsen, die Validierung und die Datumsarithmetik in einem wiederverwendbaren Value Object `Duration` oder verwende eine bereits vorhandene vergleichbare Implementierung.

Eine Duration mit dem Wert NULL definiert keine zeitgesteuerte Wartefrist.

## 5. Automatische Zustandsübergänge

Die Duration des Zielzustands definiert die Wartezeit vor dem Eintritt in diesen Zustand.

### Bezahlt → Fällig

```text
transitionDate = subscription.dueDate
               + targetStatus.duration
```

Verwende das bestehende nächste Fälligkeitsdatum des Abonnements.

Verwende für diesen Übergang nicht `subscription_status_changed`.

Die bestehende Fälligkeitsberechnung bleibt erhalten.

### Fällig → Säumig

```text
transitionDate = subscription.subscription_status_changed
               + targetStatus.duration
```

### Säumig → Beendet

```text
transitionDate = subscription.subscription_status_changed
               + targetStatus.duration
```

Ein automatischer Zustandsübergang wird fällig, sobald der aktuelle Zeitpunkt den berechneten Übergangszeitpunkt erreicht oder überschreitet.

Lies die Duration aus der jeweiligen Statusdefinition. Hinterlege keine festen Zeitintervalle im Programmcode.

Überspringe keine Zwischenzustände.

Führe pro Abonnement und Scheduler-Durchlauf maximal einen automatischen Zustandsübergang aus.

Beispiel:

| Datum | Zustand |
|---|---|
| Vor dem 1. Dezember | Bezahlt |
| 1. Dezember | Fällig |
| 8. Dezember | Säumig |
| 15. Dezember | Beendet |

## 6. Architektur und Domain Model

Orientiere dich an der bestehenden Architektur und den vorhandenen fachlichen Konzepten.

### Domain

Die Subscription kapselt ihren Lebenszyklus und stellt die Einhaltung der fachlichen Zustandsregeln sicher.

Unterscheide folgende fachliche Konzepte:

- `Subscription`: aktueller Zustand und fachliches Verhalten
- `SubscriptionStatus`: Zustandsdefinition, Duration, E-Mail-Betreff und Template String
- `Duration`: validiertes relatives Zeitintervall

Das Domain Model verantwortet:

- Zulässige Zustandsübergänge
- Ermittlung des nächsten automatischen Zustands
- Berechnung des nächsten Übergangszeitpunkts
- Durchführung von Zustandswechseln
- Zahlungsregistrierung
- Reaktivierung
- Administrative Statusänderungen

Vermeide ein Anemic Domain Model.

### Application

Die Application Services koordinieren die fachlichen Use Cases, die Persistierung und den Versand der zustandsbezogenen E-Mails.

### Infrastructure

Die Infrastructure implementiert Datenbankzugriffe, Template Rendering, E-Mail-Versand und die technische Anbindung des Schedulers.

Halte das Domain Model unabhängig von Datenbank, HTTP, SMTP und Frontend.

Führe kein generisches State-Machine-Framework und keine zusätzliche Tabelle für Zustandsübergänge ein.

Die Datenbank konfiguriert Zustände, Zeitintervalle und Nachrichten. Das Domain Model definiert die fachlichen Übergangsregeln.

## 7. Zahlungsregistrierung und Reaktivierung

Bei der Registrierung einer Zahlung wechselt das Abonnement vom Zustand `Fällig` oder `Säumig` in den Zustand `Bezahlt`.

Bei einer regulären Reaktivierung wechselt das Abonnement von `Beendet` nach `Bezahlt`.

Stelle sicher, dass das nächste reguläre Fälligkeitsdatum entsprechend den bestehenden Abonnementregeln fortgeschrieben wird.

Andernfalls könnte das Abonnement beim nächsten Scheduler-Durchlauf unmittelbar wieder in den Zustand `Fällig` wechseln.

Analysiere das Zusammenspiel zwischen Startdatum, Abonnementtyp, Abonnementzyklus und bestehender Fälligkeitsberechnung.

Verändere die bestehende fachliche Semantik der Fälligkeitsberechnung nicht ohne Rücksprache.

## 8. E-Mail-Benachrichtigungen

Jeder tatsächliche reguläre Zustandsübergang löst die E-Mail des Zielzustands aus.

Der Benachrichtigungsmechanismus arbeitet generisch und unabhängig von der Status-ID.

Beim Versand:

1. Lies den Betreff aus `subscription_status.subject`.
2. Lies den vollständigen Template String aus `subscription_status.message`.
3. Ersetze die Platzhalter durch die entsprechenden Abonnement- und Abonnentendaten.
4. Versende die resultierende E-Mail.

Verwende die bestehende Template-Syntax, den vorhandenen Rendering-Mechanismus und die bestehenden Template-Variablen wieder.

Passe den Rendering-Mechanismus gegebenenfalls so an, dass er Template Strings direkt aus der Datenbank verarbeitet.

Führe keinen ausführbaren Programmcode aus Template Strings aus.

Melde ungültige oder nicht auflösbare Platzhalter als Fehler.

Änderungen an Betreff und Nachrichtentext in der Datenbank müssen ohne Änderungen am Programmcode oder erneutes Deployment wirksam werden.

Sind `subject` und `message` beide NULL, wird keine E-Mail versendet. Ist nur eines der beiden Attribute NULL, liegt ein Konfigurationsfehler vor.

Bei administrativen Statusänderungen entscheidet der Administrator, ob die E-Mail des Zielzustands versendet werden soll.

### Erneuter Versand

Ermögliche den erneuten Versand der E-Mail des aktuellen Zustands.

Der erneute Versand verändert weder den Zustand noch den Zeitstempel des Zustandswechsels oder die Fälligkeit des Abonnements.

### Versandstatus

Unterscheide zwischen dem fachlichen Zustand des Abonnements und dem Versandstatus einer E-Mail.

Ein fehlgeschlagener E-Mail-Versand macht einen fachlich korrekten Zustandswechsel nicht rückgängig.

Analysiere die bestehende Verwendung von `reminderSent` und schlage eine einfache Lösung für die zukünftige Verwaltung des Versandstatus vor.

Entferne oder verändere `reminderSent` nicht ohne geeignete Migrationsstrategie und Rücksprache.

Dokumentiere verbleibende Konsistenzrisiken zwischen Datenbankpersistierung und SMTP-Versand.

## 9. Scheduler

Erweitere den bestehenden zeitgesteuerten Payment-Reminder-Prozess um die Auswertung der State Machine.

Für jedes Abonnement:

1. Ermittle den nächsten automatischen Zustand.
2. Prüfe, ob der Übergang fällig ist.
3. Führe den fachlichen Zustandswechsel durch.
4. Persistiere den neuen Zustand und den Zeitstempel.
5. Veranlasse den Versand der zugehörigen E-Mail.

Der Scheduler stößt Application Use Cases an und implementiert keine eigenen fachlichen Regeln.

Verhindere doppelte Zustandswechsel, konkurrierende Versandversuche und das gegenseitige Überschreiben administrativer und automatischer Zustandsänderungen.

## 10. REST API

Erweitere die bestehende REST API entsprechend den jeweiligen Vertical Slices.

Unterstütze insbesondere:

- Abrufen der verfügbaren Statusdefinitionen
- Abrufen des aktuellen Zustands und des letzten Zustandswechsels
- Abrufen des nächsten automatischen Zustands und Übergangszeitpunkts
- Registrierung einer Zahlung
- Reaktivierung
- Administrative Statusänderung
- Erneuter Versand der E-Mail des aktuellen Zustands

Verwende die bestehenden Mechanismen für Authentifizierung, Autorisierung, Validierung und Fehlerbehandlung.

Implementiere keine fachlichen Zustandsregeln in den Controllern.

## 11. Frontend

Erweitere die bestehenden Vue-Komponenten unter Verwendung von Tailwind CSS.

Behalte das bestehende, an Ernestos Website orientierte Design bei:

https://lern-gitarre-online.de/gutscheine/

### Abonnementliste

Ergänze folgende Spalten:

- Status
- Status geändert am
- Nächster automatischer Zustandswechsel

Ergänze eine Filtermöglichkeit nach Status.

Verwende konsistente Statusfarben:

- Bezahlt: Grün
- Fällig: Gelb
- Säumig: Orange
- Beendet: Rot oder Grau

### Abonnementdetails

Zeige folgende Informationen an:

- Aktueller Zustand
- Zeitpunkt des letzten Zustandswechsels
- Reguläres Fälligkeitsdatum
- Nächster automatischer Zustand
- Nächster automatischer Übergangszeitpunkt
- Versandstatus, sofern verfügbar

Ermögliche folgende Aktionen:

- Zahlung registrieren
- Abonnement reaktivieren
- Status administrativ ändern
- E-Mail des aktuellen Zustands erneut versenden

Verwende Bestätigungsdialoge für administrative Statusänderungen und den erneuten E-Mail-Versand.

Ermögliche bei administrativen Statusänderungen die Auswahl, ob die E-Mail des Zielzustands versendet werden soll.

Das Backend bleibt die maßgebliche Instanz für fachliche Regeln und Datumsberechnungen.

## 12. Tests und Qualitätssicherung

Ergänze die automatisierten Tests innerhalb jedes Vertical Slice entsprechend den bestehenden Testkonventionen.

Berücksichtige insbesondere:

- Erlaubte und unzulässige Zustandsübergänge
- Administrative Statusänderungen
- Duration-Validierung und Kalenderarithmetik
- Berechnung der Übergangszeitpunkte
- Aktualisierung des Zustandszeitstempels
- Zahlungsregistrierung und Reaktivierung
- Persistierung und Datenbankmigration
- Rendering der in der Datenbank gespeicherten E-Mail-Templates
- Erfolgreichen und fehlgeschlagenen E-Mail-Versand
- Erneuten E-Mail-Versand ohne Zustandsänderung
- Wiederholte und konkurrierende Scheduler-Ausführungen
- REST API und Autorisierung
- Erhalt der bestehenden Funktionalitäten

Verwende für zeitabhängige Tests eine kontrollierbare Uhr.

Versende während automatisierter Tests keine tatsächlichen E-Mails an Abonnenten.

Führe nach jedem Vertical Slice sämtliche bestehenden und neuen Tests aus.

## 13. Abschluss eines Vertical Slice

Erstelle nach jedem Entwicklungsschritt eine kurze Zusammenfassung mit:

- Implementierter fachlicher Funktion
- Änderungen an Domain, Application und Infrastructure
- Änderungen an REST API und Frontend
- Erforderlichen Datenbankmigrationen
- Ergänzten Tests und vollständigen Testergebnissen
- Verbleibenden Problemen und offenen Entscheidungen
- Konkreter Anleitung für den manuellen Smoke Test

Halte anschließend die Entwicklung an.

Warte auf meine Rückmeldung zum Smoke Test und meine ausdrückliche Freigabe, bevor du mit dem nächsten Vertical Slice beginnst.

Triff keine eigenständigen Entscheidungen, die bestehende fachliche Regeln verändern. Kläre solche Fragen vor der Implementierung.