# RAG Chatbot – Implementation Journal

Dieses Journal begleitet den Aufbau eines Retrieval-Augmented-Generation-Chatbots für die QuizRace-Dokumentation.
Die Arbeit ist in drei Phasen gegliedert. Dieser Commit deckt **Phase 1** ab.

## Phase 1: Wissensbasis vorbereiten

Ziele der ersten Phase:

- Markdown- und HTML-Quellen aus dem Repository automatisch einlesen.
- Inhalte vereinheitlichen (Links, Code-Blöcke, Frontmatter entfernen) und in reinen Text umwandeln.
- Texte in überschneidungsfreie, aber überlappende Chunks geeigneter Länge aufteilen.
- Das Ergebnis als strukturierte JSONL-Datei mit Metadaten („Source“, Wortanzahl, Chunk-Index) abspeichern.
- Einen wiederholbaren CLI-Workflow schaffen, der Statistiken über die erzeugte Wissensbasis ausgibt.

Diese Schritte bilden die Grundlage für Phasen 2 und 3 (Embedding/Retrieval sowie Chat-Interface).

### Umsetzungsschritte

1. Neues Python-Modul `rag_chatbot/` eingeführt, das Loader, Normalizer und Chunker kapselt.
2. CLI-Skript `scripts/build_rag_corpus.py` erstellt:
   - Standardmäßig werden relevante Dokumentationsquellen (`README.md`, alle Markdown-Dateien in `docs/` sowie HTML-Seiten in `content/`) verarbeitet.
   - Parameter für Chunk-Größe, Überlappung und Ausgabeort lassen sich über Kommandozeilenargumente anpassen.
   - Nach erfolgreichem Lauf entstehen eine JSONL-Datei (`data/rag-chatbot/corpus.jsonl`) und eine Zusammenfassung der wichtigsten Kennzahlen (Anzahl Dokumente/Chunks, durchschnittliche Wortzahl).
3. Automatisierte Tests (`tests/test_rag_chunker.py`) stellen sicher, dass Chunking und Export deterministisch funktionieren.

### Nächste Schritte (Ausblick)

- **Phase 2**: Vektorisierung der Chunks, Aufbau eines semantischen Index und Retrieval-API.
- **Phase 3**: Anbindung eines Chat-Frontends inkl. Konversations- und Kontextverwaltung.

