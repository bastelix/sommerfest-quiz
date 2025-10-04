import os
from dataclasses import replace
from pathlib import Path

from rag_chatbot import PipelineOptions, run_pipeline


def create_sample_source(tmp_path: Path, extension: str = ".md") -> Path:
    docs_dir = tmp_path / "docs"
    docs_dir.mkdir()
    sample = """# Titel\n\nDies ist ein Testdokument. Es enthält mehrere Sätze."""
    if not extension.startswith("."):
        extension = f".{extension}"
    path = docs_dir / f"sample{extension}"
    path.write_text(sample, encoding="utf-8")
    return docs_dir


def test_pipeline_builds_and_skips_when_up_to_date(tmp_path: Path) -> None:
    docs_dir = create_sample_source(tmp_path)
    corpus_path = tmp_path / "data" / "corpus.jsonl"
    index_path = tmp_path / "data" / "index.json"

    options = PipelineOptions(
        sources=[docs_dir],
        corpus_path=corpus_path,
        index_path=index_path,
        max_words=50,
        overlap=10,
    )

    first = run_pipeline(options)
    assert first.corpus is not None
    assert first.index is not None
    assert first.skipped == ()

    second = run_pipeline(options)
    assert second.corpus is None
    assert second.index is None
    assert "corpus" in second.skipped
    assert "index" in second.skipped


def test_pipeline_rebuilds_when_source_changes(tmp_path: Path) -> None:
    docs_dir = create_sample_source(tmp_path)
    corpus_path = tmp_path / "data" / "corpus.jsonl"
    index_path = tmp_path / "data" / "index.json"

    options = PipelineOptions(
        sources=[docs_dir],
        corpus_path=corpus_path,
        index_path=index_path,
        max_words=50,
        overlap=10,
    )

    run_pipeline(options)

    source_file = next(docs_dir.glob("*.md"))
    stat = source_file.stat()
    os.utime(source_file, (stat.st_atime, stat.st_mtime + 5))

    rebuilt = run_pipeline(options)
    assert rebuilt.corpus is not None
    assert rebuilt.index is not None


def test_pipeline_force_rebuilds(tmp_path: Path) -> None:
    docs_dir = create_sample_source(tmp_path)
    corpus_path = tmp_path / "data" / "corpus.jsonl"
    index_path = tmp_path / "data" / "index.json"

    options = PipelineOptions(
        sources=[docs_dir],
        corpus_path=corpus_path,
        index_path=index_path,
        max_words=50,
        overlap=10,
    )

    run_pipeline(options)

    forced = run_pipeline(replace(options, force=True))
    assert forced.corpus is not None
    assert forced.index is not None


def test_pipeline_processes_txt_sources(tmp_path: Path) -> None:
    docs_dir = create_sample_source(tmp_path, ".txt")
    corpus_path = tmp_path / "data" / "corpus.jsonl"
    index_path = tmp_path / "data" / "index.json"

    options = PipelineOptions(
        sources=[docs_dir],
        corpus_path=corpus_path,
        index_path=index_path,
        max_words=50,
        overlap=10,
    )

    result = run_pipeline(options)

    assert result.corpus is not None
    assert result.index is not None

    corpus_entries = corpus_path.read_text(encoding="utf-8").strip().splitlines()
    assert corpus_entries, "Die Wissensbasis enthält keine Einträge"
    assert any("sample.txt" in entry for entry in corpus_entries)


def test_pipeline_requires_sources(tmp_path: Path) -> None:
    corpus_path = tmp_path / "data" / "corpus.jsonl"
    index_path = tmp_path / "data" / "index.json"
    options = PipelineOptions(
        sources=[],
        corpus_path=corpus_path,
        index_path=index_path,
    )
    try:
        run_pipeline(options)
    except ValueError as exc:
        assert "Quellen" in str(exc)
    else:  # pragma: no cover - sollte nicht erreicht werden
        raise AssertionError("Pipeline hat fehlende Quellen nicht erkannt")
