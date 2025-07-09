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

## Mandanten und API

Die App kann mehrere Organisationen gleichzeitig bedienen. Jede Subdomain repräsentiert einen eigenen Mandanten mit separatem Schema in PostgreSQL. Die Schemas werden automatisch über die vorhandenen Migrationen angelegt.

### Endpunkte

- **POST `/tenants`** &ndash; legt einen neuen Mandanten an.
- **DELETE `/tenants`** &ndash; entfernt einen bestehenden Mandanten samt Schema.

Beispiel für das Anlegen eines Mandanten:

```bash
curl -X POST http://$DOMAIN/tenants \
  -H 'Content-Type: application/json' \
  -d '{"uid":"acme","schema":"acme"}'
```

### Hinweise zu Umgebungsvariablen

Die Subdomain-Funktion nutzt folgende Variablen aus `.env` oder `sample.env`:

- `DOMAIN` legt die Basis-Domain für alle Mandanten fest.
- `POSTGRES_DSN`, `POSTGRES_USER` und `POSTGRES_PASSWORD` bestimmen den Datenbankzugang.

## Weitere Seiten

* [Wie läuft das Spiel?](spielablauf.md)
* [FAQ](faq.md)
* [Datenschutz](datenschutz.md)
* [Impressum](impressum.md)
* [Lizenz](lizenz.md)
