# RAG Chatbot – Implementation Journal

Dieses Journal begleitet den Aufbau eines Retrieval-Augmented-Generation-Chatbots für die QuizRace-Dokumentation.
Die Arbeit ist in drei Phasen gegliedert. Dieser Commit deckt **Phase 1** und **Phase 2** ab.

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

## Phase 2: Semantischen Index aufbauen

Ziele der zweiten Phase:

- Die erstellte Wissensbasis automatisiert in einen numerischen Vektorraum überführen.
- Einen semantischen Index generieren, der für jede Textpassage TF-IDF-Vektoren und Normen speichert.
- Eine Retrieval-API bereitstellen, die für Anfragen ähnliche Chunks per Kosinus-Ähnlichkeit findet.
- Den Workflow als CLI-Skript reproduzierbar machen und mit Tests absichern.

### Umsetzungsschritte

1. Neues Modul `rag_chatbot/index_builder.py` eingeführt:
   - Tokenisiert alle Chunks aus der JSONL-Wissensbasis, filtert kurze Terme und baut ein Vokabular.
   - Berechnet TF-IDF-Gewichte samt Normen und speichert Index, Vokabular und Metadaten als JSON.
   - Liefert Statistiken zur Indexgröße über `IndexResult`.
2. Retrieval-Layer `rag_chatbot/retrieval.py` implementiert:
   - `SemanticIndex` lädt den JSON-Index, rekonstruiert Sparse-Vektoren und beantwortet Suchanfragen.
   - `search()` liefert sortierte `SearchResult`-Objekte inklusive Score, Text und Metadaten.
3. CLI-Tool `scripts/build_rag_index.py` ergänzt, um den Index mit optionalen Parametern (`--max-features`,
   `--min-term-length`) zu erzeugen.
4. Neue Tests (`tests/test_rag_index.py`) prüfen Indexaufbau, Fehlerfälle und Retrieval-Relevanz.

### Nächste Schritte (Ausblick)

- **Phase 3**: Anbindung eines Chat-Frontends inkl. Konversations- und Kontextverwaltung.

