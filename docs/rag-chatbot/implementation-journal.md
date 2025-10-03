# RAG Chatbot – Implementation Journal

Dieses Journal begleitet den Aufbau eines Retrieval-Augmented-Generation-Chatbots für die QuizRace-Dokumentation.
Die Arbeit ist in fünf Phasen gegliedert. Dieses Journal beschreibt die Umsetzung von **Phase 1** bis **Phase 5**.

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

## Phase 4: Auswertung und Gesprächsprotokolle

Ziele der vierten Phase:

- Chatverläufe strukturiert erfassen, um Antwortqualität und Quellenabdeckung zu bewerten.
- Eine Auswertung bereitstellen, die Statistiken über Kontexte und genutzte Quellen liefert.
- Einen reproduzierbaren Batch-Workflow schaffen, der mehrere Fragen gegen den Index laufen lässt und die Ergebnisse speichert.

### Umsetzungsschritte

1. Neues Modul `rag_chatbot/transcript.py` implementiert. Es enthält `ChatTranscript` zur Aufzeichnung von Chat-Runden, inklusive Hilfsklassen für Kontextdaten und Statistikberechnung.
2. `ChatSession` akzeptiert optional ein `ChatTranscript` und protokolliert automatisch jede Anfrage mitsamt Prompt, Antwort und Kontexttreffern.
3. CLI-Skript `scripts/rag_eval.py` ergänzt, um Fragenstapel aus einer Textdatei gegen den semantischen Index auszuführen und das erzeugte Transcript als JSON zu speichern.
4. Neue Tests (`tests/test_rag_transcript.py`) prüfen die Aufzeichnung, Statistikberechnung und den JSON-Export der Gesprächsprotokolle.

## Phase 5: Berichte und Qualitätsmetriken

Ziele der fünften Phase:

- Gesprächsprotokolle automatisiert auswerten und die wichtigsten Kennzahlen hervorheben.
- Quellenabdeckung sichtbar machen, um blinde Flecken in der Wissensbasis aufzudecken.
- Einen leicht nutzbaren CLI-Workflow schaffen, der vorhandene Transkripte zusammenfasst.

### Umsetzungsschritte

1. Neues Modul `rag_chatbot/report.py` implementiert. Es erzeugt strukturierte Berichte mit Trefferanzahl, Durchschnitts- und Maximal-Scores pro Quelle und stellt eine menschenlesbare Ausgabe bereit.
2. `ChatTranscript` um Ladefunktionen (`from_dict`, `load`) erweitert, damit gespeicherte JSON-Dateien direkt wieder eingelesen werden können. `TranscriptContext` und `TranscriptTurn` besitzen nun passende `from_dict`-Hilfsfunktionen.
3. CLI-Skript `scripts/rag_report.py` ergänzt. Es lädt ein Transcript, erzeugt den Bericht und gibt ihn als Text oder JSON aus. Parameter wie `--top` steuern die Anzahl der angezeigten Quellen.
4. Neue Tests (`tests/test_rag_report.py`) prüfen Report-Erstellung, Formatierung und den Roundtrip zwischen Speichern und Laden eines Transkripts.

## Phase 6: Automatisierter Pipeline-Workflow

Ziele der sechsten Phase:

- Wissensbasis und semantischen Index in einem wiederholbaren Schritt aktualisieren.
- Änderungen an den Dokumentationsquellen automatisch erkennen und nur notwendige Schritte neu ausführen.
- Den Workflow über ein CLI-Werkzeug ansteuerbar machen und durch Tests absichern.

### Umsetzungsschritte

1. Neues Modul `rag_chatbot/pipeline.py` entwickelt. Es bündelt Konfiguration (`PipelineOptions`), Ergebnisobjekte (`PipelineResult`) und die Funktion `run_pipeline()`, die auf Basis von Zeitstempeln entscheidet, ob Wissensbasis oder Index neu erzeugt werden müssen.
2. Skript `scripts/rag_pipeline.py` hinzugefügt. Es kombiniert Corpus- und Indexaufbau in einer Kommandozeilenoberfläche, erlaubt optionale Parameter (Chunk-Größe, Vokabularbegrenzung, `--force`) und informiert, ob Schritte übersprungen wurden.
3. Ergänzende Tests (`tests/test_rag_pipeline.py`) prüfen Neuaufbau, Überspringen unveränderter Artefakte, das Erzwingen von Rebuilds sowie Fehlerbehandlung bei fehlenden Quellen.
