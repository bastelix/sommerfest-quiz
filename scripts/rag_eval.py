#!/usr/bin/env python3
from __future__ import annotations

"""Batch-Auswertung für den RAG-Chatbot."""

import argparse
import sys
from pathlib import Path
from rag_chatbot.chat import ChatPrompt, ChatSession
from rag_chatbot.retrieval import SemanticIndex
from rag_chatbot.transcript import ChatTranscript

DEFAULT_INDEX_PATH = Path("data/rag-chatbot/index.json")
DEFAULT_OUTPUT_PATH = Path("data/rag-chatbot/transcript.json")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Führt mehrere Fragen gegen den RAG-Index aus.")
    parser.add_argument(
        "questions",
        type=Path,
        help="Textdatei mit Fragen (eine pro Zeile, Leerzeilen werden ignoriert).",
    )
    parser.add_argument("--index", type=Path, default=DEFAULT_INDEX_PATH, help="Pfad zum Index (JSON)")
    parser.add_argument(
        "--output",
        type=Path,
        default=DEFAULT_OUTPUT_PATH,
        help="JSON-Datei für das Gesprächsprotokoll",
    )
    parser.add_argument("--top-k", type=int, default=4, help="Maximale Anzahl an Kontexttreffern")
    parser.add_argument("--min-score", type=float, default=0.05, help="Mindestscore für Treffer")
    parser.add_argument("--history-limit", type=int, default=6, help="Maximale Gesprächslänge in Runden")
    return parser.parse_args()


def load_questions(path: Path) -> list[str]:
    if not path.exists():
        raise FileNotFoundError(path)
    questions: list[str] = []
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        questions.append(line)
    if not questions:
        raise ValueError("Die Fragenliste ist leer.")
    return questions


def default_responder(prompt: ChatPrompt) -> str:
    context = prompt.context
    if not context:
        return "Ich konnte keine passenden Informationen in der Wissensbasis finden."
    lines = ["Antwort basierend auf den gefundenen Dokumenten:"]
    for index, item in enumerate(context, start=1):
        source = item.metadata.get("title") or item.metadata.get("source") or item.chunk_id
        snippet = " ".join(item.text.split())
        lines.append(f"{index}. {snippet} (Quelle: {source})")
    return "\n".join(lines)


def ensure_directory(path: Path) -> None:
    if path.parent and not path.parent.exists():
        path.parent.mkdir(parents=True, exist_ok=True)


def main() -> int:
    args = parse_args()

    try:
        index = SemanticIndex(args.index)
    except FileNotFoundError:
        print(f"Index-Datei {args.index} wurde nicht gefunden.", file=sys.stderr)
        return 1

    try:
        questions = load_questions(args.questions)
    except Exception as exc:  # pragma: no cover - Fehlerausgabe ist trivial
        print(f"Fehler beim Laden der Fragen: {exc}", file=sys.stderr)
        return 1

    transcript = ChatTranscript()
    session = ChatSession(
        index,
        responder=default_responder,
        history_limit=args.history_limit,
        top_k=args.top_k,
        min_score=args.min_score,
        transcript=transcript,
    )

    for question in questions:
        turn = session.send(question)
        print(f"Frage: {question}")
        print(f"Antwort:\n{turn.response}\n")

    if args.output:
        ensure_directory(args.output)
        transcript.save(args.output)
        stats = transcript.stats()
        print(
            f"Gesprächsprotokoll gespeichert in {args.output}"
            f" (Runden: {stats.turns}, eindeutige Quellen: {stats.unique_sources})."
        )

    return 0


if __name__ == "__main__":  # pragma: no cover - Skript-Einstiegspunkt
    sys.exit(main())
