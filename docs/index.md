---
layout: default
title: Projekt
---

# QuizRace

Das **QuizRace** ist eine Web-App für Veranstaltungen. Die Anwendung basiert
auf dem Slim Framework und nutzt UIkit3 für ein responsives Design. Fragenkataloge,
Teilnehmer und Ergebnisse werden in einer PostgreSQL-Datenbank gespeichert und
können per JSON importiert oder exportiert werden.

Die Anwendung benötigt PHP 8.2 oder höher.

Die Entwicklung entstand als Machbarkeitsstudie, um das Potenzial moderner
Code-Assistenten in der Praxis zu testen. Barrierefreiheit, Datenschutz und eine
hohe Performance standen dabei im Mittelpunkt.

Weitere Highlights sind:

- **Flexibel einsetzbar**: Kataloge im JSON-Format lassen sich einfach austauschen.
- **Sechs Fragetypen**: Sortieren, Zuordnen, Multiple Choice, Swipe-Karten, Foto mit Texteingabe und "Hätten Sie es gewusst?"-Karten.
- **QR-Code-Login & Dunkelmodus** für komfortables Spielen auf allen Geräten.
- **Persistente Speicherung** in PostgreSQL.

## Mandantenfunktionen

Die App kann mehrere Organisationen gleichzeitig bedienen. Jeder Mandant wird 
durch eine Subdomain repräsentiert und erhält beim Anlegen ein eigenes Schema 
in PostgreSQL, das mittels Migrationen automatisch erstellt wird.

### API-Endpunkte

- **POST `/tenants`** – legt einen neuen Mandanten samt Schema an.
- **DELETE `/tenants`** – entfernt einen bestehenden Mandanten und löscht das
  Schema.
- **POST `/api/renew-ssl`** – erneuert das Zertifikat der Admin-Domain und startet den Hauptcontainer neu.
- **POST `/api/tenants/{slug}/renew-ssl`** – erneuert das SSL-Zertifikat der Subdomain und triggert dabei den internen Reload-Service.

### Beispiel-Anfragen

```bash
curl -X POST http://$DOMAIN/tenants \
  -H 'Content-Type: application/json' \
  -d '{"uid":"acme","schema":"acme"}'
```

```bash
curl -X DELETE http://$DOMAIN/tenants \
  -H 'Content-Type: application/json' \
  -d '{"uid":"acme"}'
```

### Hinweise zu Umgebungsvariablen

Die Mandanten-Logik nutzt folgende Variablen aus `.env` oder `sample.env`:

- `COMPOSE_PROJECT_NAME` hält den Docker-Compose-Projektnamen stabil, damit Container und Volumes bei Updates weiterverwendet werden.
- `DOMAIN` legt die Basis-Domain für alle Mandanten fest.
- `MAIN_DOMAIN` definiert die Hauptdomain des Quiz-Containers.
- `POSTGRES_DSN`, `POSTGRES_USER` und `POSTGRES_PASSWORD` bestimmen den Datenbankzugang.

### Anmelde-Workflow

Setze in `.env` zuerst `MAIN_DOMAIN` auf die öffentliche Marketing-Domain:

```bash
MAIN_DOMAIN=quiz.example
```

Ein neuer Mandant lässt sich anschließend mit
`scripts/create_tenant.sh <subdomain>` registrieren. Alternativ
funktioniert auch ein `POST` auf `/tenants`. Beide Aufrufe müssen von der
Hauptdomain aus erfolgen, andernfalls antwortet der Server mit `403`.

Die Marketing-Seite `/landing` ist nur auf der in
`MAIN_DOMAIN` hinterlegten Domain verfügbar. Wird eine Subdomain
aufgerufen, erhält der Browser stattdessen einen 404-Status.

## Service-Accounts

Service-Accounts eignen sich für automatisierte Abläufe. Sie lassen sich wie normale Benutzer über `/users.json` anlegen. Dabei wird als Rolle `service-account` gesetzt.

Authentifiziert wird ein Service-Account über die JSON-Variante von `/login`. Die Sitzungskücke aus der Antwort muss bei weiteren API-Aufrufen mitgesendet werden.

```bash
curl -c cookies.txt -X POST http://$DOMAIN/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"robot","password":"secret"}'
```

Soll der Account für den Onboarding-Assistenten genutzt werden
(Variablen `SERVICE_USER` und `SERVICE_PASS`), benötigt er mindestens die Rolle
`service-account`. Damit kann der Assistent Standarddaten importieren (`POST
/restore-default`).

```bash
curl -X POST http://$DOMAIN/users.json \
  -H 'Content-Type: application/json' \
  -d '[{"username":"robot","password":"secret","role":"service-account","active":true}]'
```

## Weitere Seiten

* [Wie läuft das Spiel?](spielablauf.md)
* [FAQ](faq.md)
* [Datenschutz](datenschutz.md)
* [Impressum](impressum.md)
* [Lizenz](lizenz.md)
* [Migrationen](migrationen.md)
* [Landing-Seiten-Stile](landing-style-overrides.md)
* [Marketing- vs. Page-Editor-System](page-systems-grundregel.md)
