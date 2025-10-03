#!/usr/bin/env python3
from __future__ import annotations

import argparse
from pathlib import Path
from typing import Iterable

from rag_chatbot import ChatMessage, ChatPrompt, ChatSession, ChatTurn, SemanticIndex


class ContextResponder:
    """Einfache Heuristik, die die gefundenen Chunks zusammenfasst."""

    def __call__(self, prompt: ChatPrompt) -> str:
        question = _extract_user_message(prompt.messages)
        if not prompt.context:
            return (
                "Ich konnte keine passenden Informationen in der Dokumentation finden. "
                "Bitte stelle deine Frage anders oder schränke das Thema ein."
            )

        lines = [
            "Basierend auf der Wissensbasis habe ich folgende Hinweise gefunden:",
        ]
        for index, item in enumerate(prompt.context, start=1):
            source = _format_source(item.metadata)
            snippet = _summarise(item.text)
            lines.append(f"{index}. {source}: {snippet}")

        if question:
            lines.append("")
            lines.append(f"Frage: {question}")
        return "\n".join(lines)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Interaktive Konsole für den RAG-Chatbot")
    parser.add_argument(
        "--index",
        type=Path,
        default=Path("data/rag-chatbot/index.json"),
        help="Pfad zum zuvor erzeugten semantischen Index",
    )
    parser.add_argument("--top-k", type=int, default=4, help="Anzahl der Kontext-Chunks pro Frage")
    parser.add_argument(
        "--min-score",
        type=float,
        default=0.05,
        help="Minimale Ähnlichkeit, damit ein Chunk berücksichtigt wird",
    )
    parser.add_argument(
        "--history-limit",
        type=int,
        default=6,
        help="Anzahl der vorangegangenen Benutzer/Assistenten-Paare, die behalten werden",
    )
    return parser


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()

    index = SemanticIndex(args.index)
    session = ChatSession(
        index,
        responder=ContextResponder(),
        history_limit=args.history_limit,
        top_k=args.top_k,
        min_score=args.min_score,
    )

    print("QuizRace RAG-Chatbot – Tippe 'quit' oder 'exit' zum Beenden.")
    while True:
        try:
            user_input = input("Du: ")
        except (KeyboardInterrupt, EOFError):
            print()
            break
        if user_input.strip().lower() in {"quit", "exit"}:
            break
        if not user_input.strip():
            continue

        turn = session.send(user_input)
        print(_format_turn(turn))


def _format_turn(turn: ChatTurn) -> str:
    context_lines = []
    for index, item in enumerate(turn.prompt.context, start=1):
        source = _format_source(item.metadata)
        context_lines.append(f"[{index}] {source} (Score: {item.score:.2f})")
    formatted = []
    if context_lines:
        formatted.append("Kontext")
        formatted.extend(context_lines)
    formatted.append("")
    formatted.append(f"Bot: {turn.response}")
    return "\n".join(formatted)


def _extract_user_message(messages: Iterable[ChatMessage]) -> str:
    for message in reversed(tuple(messages)):
        role = getattr(message, "role", None)
        if role == "user":
            return getattr(message, "content", "")
    return ""


def _format_source(metadata: dict[str, object]) -> str:
    title = metadata.get("title")
    if title:
        return str(title)
    source = metadata.get("source")
    if source:
        return str(source)
    return metadata.get("id", "Unbekannte Quelle")


def _summarise(text: str, limit: int = 320) -> str:
    condensed = " ".join(text.split())
    if len(condensed) <= limit:
        return condensed
    return condensed[: limit - 1] + "…"


if __name__ == "__main__":
    main()
