# Menü-Editor: UX-Verbesserungen

Diese Notiz fasst den aktuellen Stand des Menüeditors zusammen und leitet konkrete Umsetzungsschritte ab. Sie orientiert sich an den vorhandenen JavaScript-Bausteinen von edocs, damit die Weiterentwicklung ohne Technologiebruch erfolgen kann.

## Status quo: funktional, aber kognitiv überladen

* Jede Zeile kombiniert Label, Link, Parent, Layout, Icon, Locale, Startpage, Extern, Aktiv sowie Erklärungstexte. Für den Standardfall ist das zu viel auf einmal.
* Basis- und Expertenfunktionen stehen gleichrangig nebeneinander; einfache Nutzer:innen finden den Einstieg schwer.
* Hierarchien sind nur textuell erkennbar. Drag & Drop über Ebenen hinweg hat wenig visuelles Feedback.
* Keine Live-Rückmeldung: Startseiten-Flag, externe Links oder Layout-Auswahl zeigen keine unmittelbare Wirkung.
* Erklärungsspalte (Titel/Beschreibung/Subline) ist unklar zugeordnet; Nutzen im Frontend ist nicht ersichtlich.

## Akzeptanzkriterien für einen intuitiven Editor

1. **Standardfall priorisieren:** Menüpunkt anlegen, benennen, sichtbar machen muss ohne Ablenkung möglich sein.
2. **Hierarchie sichtbar machen:** Baumstruktur mit klaren Eltern-/Kind-Beziehungen.
3. **Progressive Offenlegung:** Erweiterte Optionen nur bei Bedarf einblendbar.
4. **Frontend-Nähe:** Vorschau oder zumindest klares Feedback zum Verhalten (Startseite, extern, Layout).
5. **Fehlbedienung vermeiden:** Ungültige Eltern, fehlerhafte URLs oder doppelte Slugs früh validieren.

## Zielbild

* Zweistufiger Editor: Basisansicht für 80 % der Fälle, aufklappbare „Erweitert“-Sektion pro Eintrag.
* Tree-View statt Tabellenzeilen, inklusive Einrückung und begrenztem Drag & Drop innerhalb der sichtbaren Hierarchie.
* Inline-Bearbeitung des Labels, Schnelleinstellungen über Hover-Icons (Sichtbarkeit, Einstellungen).
* Live-Validierung für Links und Slugs; Startseiten-Flag wird visuell hervorgehoben.

## Umsetzungsvorschlag (inkrementell)

1. **Tree-Komponente etablieren**
   * Menüstruktur als JSON laden und in eine sortierbare Tree-View rendern (z. B. bestehende sortable-Tree-Komponente aus dem Stack nutzen).
   * Drag & Drop nur innerhalb der Baumansicht erlauben; visuelle Einrückung für Parent/Child.

2. **Basis-/Expertenmodus trennen**
   * Basisansicht zeigt nur Titel, Ziel (Seite/URL/Anker) und Sichtbarkeit.
   * „Erweitert“ als aufklappbare Sektion mit Layout, Icon, Locale, Startseite, Extern, SEO-/Meta-Texte.

3. **Direkte Rückmeldung einbauen**
   * Live-Validierung: URL-Format, doppelte Slugs, ungültige Parent-Zuweisungen.
   * Startseiten-Status mit prominentem Badge; Warnung bei mehreren Startseiten.
   * Optional: kleine Navigation-Preview in einer Sidebar.

4. **Interaktionsdetails**
   * Inline-Editing für Label; Speichern-Button auf Root-Level (Änderungen gepuffert, explizites Speichern).
   * Hover-Aktionen: ⚙️ für Einstellungen (öffnet „Erweitert“), 👁 für Sichtbarkeit.
   * Tastaturfokus und ARIA-Rollen beachten, damit der Tree zugänglich bleibt.

5. **Risikoarme Migration**
   * Zunächst nur Darstellung und Interaktion umstellen; Backend-API unverändert lassen.
   * Feature-Flag für den neuen Editor, bis die Validierung und Preview stabil sind.

## Deliverables

* Tree-basierter Menüeditor mit Basis-/Expertenansicht.
* Live-Validierung und Startseiten-Indikator.
* Optionaler Preview-Modus zur schnellen Rückmeldung über Navigationsverhalten.
