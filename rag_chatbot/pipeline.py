from __future__ import annotations

"""Automatisierter Workflow zum Erzeugen von Wissensbasis und Index."""

from dataclasses import dataclass
from pathlib import Path
from typing import Sequence

from .corpus_builder import BuildOptions, BuildResult, build_corpus
from .index_builder import IndexOptions, IndexResult, build_index
from .loader import iter_source_files


@dataclass(frozen=True)
class PipelineOptions:
    """Einstellungen für den End-to-End-Aufbau der Wissensbasis."""

    sources: Sequence[Path]
    corpus_path: Path
    index_path: Path
    max_words: int = 180
    overlap: int = 40
    max_features: int | None = None
    min_term_length: int = 2
    force: bool = False


@dataclass(frozen=True)
class PipelineResult:
    """Ergebnis des Pipeline-Durchlaufs."""

    corpus: BuildResult | None
    index: IndexResult | None
    skipped: tuple[str, ...]


def run_pipeline(options: PipelineOptions) -> PipelineResult:
    """Führt den kompletten Aufbau inklusive Index aus."""

    source_files = _collect_source_files(options.sources)
    skipped: list[str] = []

    corpus_result: BuildResult | None
    if options.force or _needs_rebuild(options.corpus_path, source_files):
        corpus_options = BuildOptions(
            sources=options.sources,
            output_path=options.corpus_path,
            max_words=options.max_words,
            overlap=options.overlap,
        )
        corpus_result = build_corpus(corpus_options)
    else:
        corpus_result = None
        skipped.append("corpus")

    index_dependencies: list[Path] = [options.corpus_path]
    index_result: IndexResult | None
    if options.force or corpus_result is not None or _needs_rebuild(options.index_path, index_dependencies):
        index_options = IndexOptions(
            corpus_path=options.corpus_path,
            output_path=options.index_path,
            max_features=options.max_features,
            min_term_length=options.min_term_length,
        )
        index_result = build_index(index_options)
    else:
        index_result = None
        skipped.append("index")

    return PipelineResult(corpus=corpus_result, index=index_result, skipped=tuple(skipped))


def _collect_source_files(sources: Sequence[Path]) -> tuple[Path, ...]:
    if not sources:
        raise ValueError("Es wurden keine Quellen übergeben.")
    files: list[Path] = [Path(path) for path in iter_source_files(sources)]
    if not files:
        raise ValueError("In den angegebenen Quellen wurden keine unterstützten Dateien gefunden.")
    return tuple(files)


def _needs_rebuild(target: Path, dependencies: Sequence[Path]) -> bool:
    if not target.exists():
        return True
    try:
        target_mtime = target.stat().st_mtime
    except FileNotFoundError:  # pragma: no cover - Race condition
        return True
    for dependency in dependencies:
        try:
            dep_mtime = dependency.stat().st_mtime
        except FileNotFoundError:
            continue
        if dep_mtime > target_mtime:
            return True
    return False


__all__ = ["PipelineOptions", "PipelineResult", "run_pipeline"]

