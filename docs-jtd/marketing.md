---
layout: default
title: Vorteile & Nutzen
nav_order: 2
parent: Start & Überblick
toc: true
---

# Vorteile & Nutzen

## Überblick

- **Flexibel einsetzbar:** Fragenkataloge im JSON-Format lassen sich bequem austauschen oder erweitern.
- **Sechs Fragetypen:** Sortieren, Zuordnen, Multiple Choice, Swipe-Karten, Foto mit Texteingabe und "Hätten Sie es gewusst?"-Karten bieten Abwechslung f\u00fcr jede Zielgruppe.
- **QR-Code-Login & Dunkelmodus:** Optionaler QR-Code-Login für schnelles Anmelden und ein zuschaltbares dunkles Design steigern den Komfort.
- **Persistente Speicherung:** Konfigurationen, Kataloge und Ergebnisse liegen in einer PostgreSQL-Datenbank.

## Highlights

- **Einfache Installation** – Nur Composer-Abhängigkeiten installieren und einen PHP-Server starten.
- **Intuitives UI** – Komplett auf UIkit3 basierendes Frontend mit flüssigen Animationen und responsive Design.
- **Stark anpassbar** – Farben, Logo und Texte lassen sich über `data/config.json` anpassen.
- **Backup per JSON** – Alle Daten lassen sich exportieren und wieder importieren.
- **Automatische Bildkompression** – Hochgeladene Fotos werden standardmäßig verkleinert und komprimiert.
- **Rätselwort und Foto-Einwilligung** – Optionales Puzzlewort-Spiel mit DSGVO-konformen Foto-Uploads.

## Fokus der Entwicklung

- **Barrierefreiheit**: Die App ist für alle zugänglich, auch für Menschen mit Einschränkungen.
- **Datenschutz**: Die Daten sind sicher und werden vertraulich behandelt.
- **Schnelle und stabile Nutzung**: Auch bei vielen Teilnehmenden läuft die App zuverlässig.
- **Einfache Bedienung**: Die Nutzung ist leicht und selbsterklärend.
- **Geräteunabhängigkeit**: Funktioniert auf allen Geräten – Handy, Tablet oder PC.
- **Nachhaltigkeit**: Die Umsetzung ist ressourcenschonend.
- **Offene Schnittstellen**: Die App lässt sich problemlos mit anderen Systemen verbinden.

Dieses Projekt zeigt, wie Mensch und KI gemeinsam neue digitale Möglichkeiten schaffen können.

## Anmeldung neuer Mandanten

Setze in der Datei `.env` den Wert `MAIN_DOMAIN` auf deine Hauptadresse.
Nur unter dieser Domain wird die Marketing-Seite `/landing` angezeigt;
Subdomains liefern hier einen 404-Status.

Um einen neuen Mandanten anzulegen, führe auf dem Hostsystem
`scripts/create_tenant.sh <subdomain>` aus oder sende einen `POST` an
`/tenants`. Anschließend startest du mit
`scripts/onboard_tenant.sh <subdomain>` den separaten Container, der das
SSL-Zertifikat anfordert. Diese Aufrufe funktionieren ausschließlich über die in
`MAIN_DOMAIN` konfigurierte Domain.

