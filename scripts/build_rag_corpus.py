#!/usr/bin/env python3
from __future__ import annotations

import argparse
from pathlib import Path

from rag_chatbot import BuildOptions, build_corpus

DEFAULT_SOURCES = [
    Path("README.md"),
    Path("docs"),
    Path("content"),
]
DEFAULT_OUTPUT = Path("data/rag-chatbot/corpus.jsonl")


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Erstellt die Wissensbasis für den RAG-Chatbot.")
    parser.add_argument(
        "sources",
        nargs="*",
        type=Path,
        default=DEFAULT_SOURCES,
        help="Dateien oder Verzeichnisse, die eingelesen werden sollen",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=DEFAULT_OUTPUT,
        help="Pfad der zu erzeugenden JSONL-Datei",
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
        help="Anzahl der überlappenden Wörter zwischen zwei Chunks",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_arguments()
    options = BuildOptions(
        sources=args.sources,
        output_path=args.output,
        max_words=args.max_words,
        overlap=args.overlap,
    )
    result = build_corpus(options)
    print(
        "Wissensbasis erzeugt:",
        f"{result.documents} Dokument(e)",
        f"{result.chunks} Chunk(s)",
        f"Ø {result.average_words} Wörter pro Chunk",
        f"Datei: {result.output_path}",
        sep="\n",
    )


if __name__ == "__main__":
    main()

