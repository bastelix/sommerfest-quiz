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

## Phase 3: Chat-Interface mit Kontextverwaltung

Ziele der dritten Phase:

- Eine Konversationsschicht ergänzen, die vorherige Nutzer- und Assistentenantworten verwaltet.
- Kontext aus dem semantischen Index automatisch zum Prompt hinzufügen.
- Ein CLI-Frontend bereitstellen, das die wichtigsten Parameter (Top-K, Mindestscore, Verlaufslänge) konfigurierbar macht.

### Umsetzungsschritte

1. Neues Modul `rag_chatbot/chat.py` mit `ChatSession`, `ChatPrompt` und `ChatTurn` implementiert. Die Klasse fasst Kontextpassagen
   zusammen, verwaltet die Historie in einem begrenzten Fenster und liefert strukturierte Prompts für LLM-Aufrufe.
2. CLI-Tool `scripts/rag_chat.py` ergänzt. Es lädt den Index, startet eine interaktive Konsole und generiert Antworten auf Basis
   der gefundenen Kontext-Chunks. Parameter wie `--top-k`, `--min-score` und `--history-limit` steuern Verhalten und Trefferqualität.
3. Automatisierter Test `tests/test_rag_chat.py` sichert das neue Verhalten ab (Prompt-Aufbau, Kontextintegration,
   Historienbegrenzung und Fehlerfälle).

