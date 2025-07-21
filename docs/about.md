# Projekt\u00fcberblick

Das QuizRace ist eine leichtgewichtige Web-Applikation zum Durchf\u00fchren von Quizrunden auf Veranstaltungen. Die Anwendung setzt auf das Slim Framework und nutzt UIkit3 f\u00fcr ein responsives Design. Fragenkataloge, Teilnehmer und Ergebnisse werden in einer PostgreSQL-Datenbank gespeichert und k\u00f6nnen per JSON importiert oder exportiert werden.

## Spielregeln

1. Jedes Team meldet sich \u00fcber einen QR-Code oder manuell mit einem Namen an.
2. Ein Fragenkatalog kann Sortier-, Zuordnungs- und Multiple-Choice-Aufgaben enthalten.
3. Die Punktezahl h\u00e4ngt von der Anzahl korrekt gel\u00f6ster Aufgaben ab.
4. Optional kann zu jeder Frage ein Buchstabe f\u00fcr ein R\u00e4tselwort vergeben werden.
5. Nachdem alle Fragen beantwortet wurden, wird die Gesamtwertung angezeigt. Bei aktivem Wettkampfmodus sind Wiederholungen nicht m\u00f6glich.

Weitere Details zur Einrichtung finden Sie im [Haupt-README](../README.md).
