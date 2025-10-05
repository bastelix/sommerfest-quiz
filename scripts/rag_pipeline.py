#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent.parent
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

from rag_chatbot import PipelineOptions, run_pipeline

DEFAULT_SOURCES = [
    Path("README.md"),
    Path("docs"),
    Path("content"),
]
DEFAULT_CORPUS = Path("data/rag-chatbot/corpus.jsonl")
DEFAULT_INDEX = Path("data/rag-chatbot/index.json")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Führt Wissensbasis- und Indexaufbau in einem Schritt aus.",
    )
    parser.add_argument(
        "sources",
        nargs="*",
        type=Path,
        default=DEFAULT_SOURCES,
        help="Dateien oder Ordner mit Dokumentationsinhalten",
    )
    parser.add_argument(
        "--corpus",
        type=Path,
        default=DEFAULT_CORPUS,
        help="Zieldatei für die Wissensbasis (JSONL)",
    )
    parser.add_argument(
        "--index",
        type=Path,
        default=DEFAULT_INDEX,
        help="Zieldatei für den semantischen Index (JSON)",
    )
    parser.add_argument(
        "--max-words",
        type=int,
        default=180,
        help="Maximale Wortanzahl pro Chunk",
    )
    parser.add_argument(
        "--overlap",
        type=int,
        default=40,
        help="Überlappung der Chunks in Wörtern",
    )
    parser.add_argument(
        "--max-features",
        type=int,
        default=None,
        help="Optionales Limit für das TF-IDF-Vokabular",
    )
    parser.add_argument(
        "--min-term-length",
        type=int,
        default=2,
        help="Minimale Länge eines Terms für das Vokabular",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Erzwingt den Neuaufbau unabhängig von Zeitstempeln",
    )
    return parser


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()

    options = PipelineOptions(
        sources=args.sources,
        corpus_path=args.corpus,
        index_path=args.index,
        max_words=args.max_words,
        overlap=args.overlap,
        max_features=args.max_features,
        min_term_length=args.min_term_length,
        force=args.force,
    )

    result = run_pipeline(options)

    if result.corpus:
        corpus = result.corpus
        print(
            "Wissensbasis aktualisiert:",
            f"Dokumente: {corpus.documents}",
            f"Chunks: {corpus.chunks}",
            f"Ø Wörter pro Chunk: {corpus.average_words}",
            f"Datei: {corpus.output_path}",
            sep="\n",
        )
    else:
        print("Wissensbasis ist bereits aktuell.")

    if result.index:
        index = result.index
        print(
            "Index aktualisiert:",
            f"Chunks: {index.chunks}",
            f"Vokabulargröße: {index.vocabulary_size}",
            f"Datei: {index.output_path}",
            sep="\n",
        )
    else:
        print("Index ist bereits aktuell.")


if __name__ == "__main__":  # pragma: no cover - Skripteintrittspunkt
    main()

