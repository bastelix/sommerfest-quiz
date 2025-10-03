from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from rag_chatbot import IndexOptions, build_index
from rag_chatbot.retrieval import SemanticIndex


def _write_corpus(path: Path, entries: list[dict[str, object]]) -> None:
    with path.open("w", encoding="utf-8") as handle:
        for entry in entries:
            json.dump(entry, handle, ensure_ascii=False)
            handle.write("\n")


def test_build_index_creates_file(tmp_path: Path) -> None:
    corpus = tmp_path / "corpus.jsonl"
    entries = [
        {
            "id": "doc:0000",
            "source": "doc.md",
            "title": "Python Einstieg",
            "chunk_index": 0,
            "word_count": 4,
            "text": "Python macht Spaß beim Programmieren.",
        },
        {
            "id": "doc:0001",
            "source": "doc.md",
            "title": "PHP Einstieg",
            "chunk_index": 1,
            "word_count": 4,
            "text": "PHP eignet sich für Webanwendungen.",
        },
    ]
    _write_corpus(corpus, entries)

    output = tmp_path / "index.json"
    options = IndexOptions(corpus_path=corpus, output_path=output, min_term_length=3)
    result = build_index(options)

    assert output.exists()
    assert result.chunks == 2
    assert result.vocabulary_size > 0


def test_build_index_requires_non_empty_corpus(tmp_path: Path) -> None:
    corpus = tmp_path / "empty.jsonl"
    corpus.write_text("", encoding="utf-8")
    output = tmp_path / "index.json"

    options = IndexOptions(corpus_path=corpus, output_path=output)
    try:
        build_index(options)
    except ValueError as exc:  # pragma: no cover - branch expected in test
        assert "leer" in str(exc)
    else:  # pragma: no cover
        raise AssertionError("Fehler wurde nicht ausgelöst")


def test_semantic_index_returns_best_match(tmp_path: Path) -> None:
    corpus = tmp_path / "corpus.jsonl"
    entries = [
        {
            "id": "doc:0000",
            "source": "doc.md",
            "title": "Python Einstieg",
            "chunk_index": 0,
            "word_count": 6,
            "text": "Python ist eine beliebte Programmiersprache für Datenanalyse.",
        },
        {
            "id": "doc:0001",
            "source": "doc.md",
            "title": "PHP Einstieg",
            "chunk_index": 1,
            "word_count": 6,
            "text": "PHP ist weit verbreitet im Web und in Content-Management-Systemen.",
        },
    ]
    _write_corpus(corpus, entries)

    output = tmp_path / "index.json"
    options = IndexOptions(corpus_path=corpus, output_path=output)
    build_index(options)

    index = SemanticIndex(output)
    results = index.search("Wie kann ich mit Python Daten analysieren?", top_k=1)

    assert results, "Es wurde kein Treffer gefunden"
    assert results[0].chunk_id == "doc:0000"
    assert "Python" in results[0].text

