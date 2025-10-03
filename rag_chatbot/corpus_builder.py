from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Sequence

from .chunker import Chunk, chunk_paragraphs, split_into_paragraphs
from .loader import Document, load_documents


@dataclass(frozen=True)
class BuildOptions:
    sources: Sequence[Path]
    output_path: Path
    max_words: int = 180
    overlap: int = 40


@dataclass(frozen=True)
class BuildResult:
    documents: int
    chunks: int
    average_words: float
    output_path: Path


def build_corpus(options: BuildOptions) -> BuildResult:
    documents = load_documents(options.sources)
    chunks: list[dict[str, object]] = []

    for document in documents:
        paragraphs = split_into_paragraphs(document.text)
        for index, chunk in enumerate(
            chunk_paragraphs(paragraphs, max_words=options.max_words, overlap=options.overlap)
        ):
            chunks.append(
                {
                    "id": f"{document.path.stem}:{index:04d}",
                    "source": document.source,
                    "title": document.title,
                    "chunk_index": index,
                    "word_count": chunk.word_count,
                    "text": _format_chunk_text(chunk),
                }
            )

    _write_jsonl(options.output_path, chunks)

    total_words = sum(chunk["word_count"] for chunk in chunks)
    average_words = total_words / len(chunks) if chunks else 0.0
    return BuildResult(
        documents=len(documents),
        chunks=len(chunks),
        average_words=round(average_words, 2),
        output_path=options.output_path,
    )


def _format_chunk_text(chunk: Chunk) -> str:
    return chunk.text.strip()


def _write_jsonl(path: Path, chunks: Iterable[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as handle:
        for item in chunks:
            json.dump(item, handle, ensure_ascii=False)
            handle.write("\n")

