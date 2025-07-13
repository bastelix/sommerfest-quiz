---
layout: default
title: Projekt
---

# Sommerfest-Quiz

Das **Sommerfest-Quiz** ist eine Web-App für Veranstaltungen. Die Anwendung basiert
auf dem Slim Framework und nutzt UIkit3 für ein responsives Design. Fragenkataloge,
Teilnehmer und Ergebnisse werden in einer PostgreSQL-Datenbank gespeichert und
können per JSON importiert oder exportiert werden.

Die Entwicklung entstand als Machbarkeitsstudie, um das Potenzial moderner
Code-Assistenten in der Praxis zu testen. Barrierefreiheit, Datenschutz und eine
hohe Performance standen dabei im Mittelpunkt.

Weitere Highlights sind:

- **Flexibel einsetzbar**: Kataloge im JSON-Format lassen sich einfach austauschen.
- **Fünf Fragetypen**: Sortieren, Zuordnen, Multiple Choice, Swipe-Karten und Foto mit Texteingabe.
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

- `DOMAIN` legt die Basis-Domain für alle Mandanten fest.
- `POSTGRES_DSN`, `POSTGRES_USER` und `POSTGRES_PASSWORD` bestimmen den Datenbankzugang.

## Service-Accounts

Service-Accounts eignen sich für automatisierte Abläufe. Sie lassen sich wie normale Benutzer über `/users.json` anlegen. Dabei wird als Rolle `service-account` gesetzt.

Authentifiziert wird ein Service-Account über die JSON-Variante von `/login`. Die Sitzungskücke aus der Antwort muss bei weiteren API-Aufrufen mitgesendet werden.

```bash
curl -c cookies.txt -X POST http://$DOMAIN/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"robot","password":"secret"}'
```

## Weitere Seiten

* [Wie läuft das Spiel?](spielablauf.md)
* [FAQ](faq.md)
* [Datenschutz](datenschutz.md)
* [Impressum](impressum.md)
* [Lizenz](lizenz.md)
