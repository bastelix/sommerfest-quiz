#!/usr/bin/env python3
from __future__ import annotations

import argparse
from pathlib import Path

from rag_chatbot import IndexOptions, build_index

DEFAULT_CORPUS = Path("data/rag-chatbot/corpus.jsonl")
DEFAULT_OUTPUT = Path("data/rag-chatbot/index.json")


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Erzeugt den semantischen Index für den RAG-Chatbot.",
    )
    parser.add_argument(
        "corpus",
        type=Path,
        nargs="?",
        default=DEFAULT_CORPUS,
        help="Pfad zur JSONL-Wissensbasis",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=DEFAULT_OUTPUT,
        help="Zieldatei für den Index",
    )
    parser.add_argument(
        "--max-features",
        type=int,
        default=None,
        help="Begrenzt die Anzahl der Terme im Vokabular",
    )
    parser.add_argument(
        "--min-term-length",
        type=int,
        default=2,
        help="Minimale Länge eines Terms, damit er in den Index aufgenommen wird",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_arguments()
    options = IndexOptions(
        corpus_path=args.corpus,
        output_path=args.output,
        max_features=args.max_features,
        min_term_length=args.min_term_length,
    )
    result = build_index(options)
    print(
        "Index erzeugt:",
        f"{result.chunks} Chunk(s) verarbeitet",
        f"{result.vocabulary_size} Terme im Vokabular",
        f"Datei: {result.output_path}",
        sep="\n",
    )


if __name__ == "__main__":
    main()

