from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from rag_chatbot import BuildOptions, build_corpus
from rag_chatbot.chunker import chunk_paragraphs, split_into_paragraphs


def test_chunker_respects_limits() -> None:
    text = "\n\n".join(["Paragraph " + str(i) + " " + "wort " * 60 for i in range(3)])
    paragraphs = split_into_paragraphs(text)
    chunks = list(chunk_paragraphs(paragraphs, max_words=100, overlap=10))
    assert chunks, "Es wurden keine Chunks erzeugt"
    for index, chunk in enumerate(chunks):
        assert chunk.word_count <= 100
        if index < len(chunks) - 1:
            next_chunk = chunks[index + 1]
            overlap = set(chunk.text.split()[-10:])
            assert overlap.intersection(next_chunk.text.split()), "Overlap fehlt"


def test_build_corpus_creates_jsonl(tmp_path: Path) -> None:
    source_dir = tmp_path / "docs"
    source_dir.mkdir()
    sample = """# Titel\n\nDies ist ein Testdokument.\n\n- Liste Eins\n- Liste Zwei\n\nWeitere Inhalte."""
    (source_dir / "sample.md").write_text(sample, encoding="utf-8")

    output = tmp_path / "corpus.jsonl"
    options = BuildOptions(sources=[source_dir], output_path=output, max_words=50, overlap=5)
    result = build_corpus(options)

    assert result.documents == 1
    assert output.exists()

    lines = output.read_text(encoding="utf-8").strip().splitlines()
    assert lines, "Ausgabedatei ist leer"

    for line in lines:
        payload = json.loads(line)
        assert payload["source"].endswith("sample.md")
        assert payload["word_count"] > 0
        assert payload["text"].strip()

