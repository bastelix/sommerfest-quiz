#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any, Dict, Optional

from rag_chatbot import ChatPrompt, ChatSession, ChatTurn, SemanticIndex


class ChatServiceResponder:
    """Reicht Prompts an den konfigurierten Chat-Service weiter."""

    NO_CONTEXT_MESSAGE = (
        "Ich konnte keine passenden Informationen in der Dokumentation finden. "
        "Bitte stelle deine Frage anders oder schränke das Thema ein."
    )

    def __init__(
        self,
        endpoint: Optional[str] = None,
        *,
        timeout: float = 30.0,
        api_key: Optional[str] = None,
    ) -> None:
        self._endpoint = endpoint or os.environ.get("RAG_CHAT_SERVICE_URL")
        if not self._endpoint:
            raise RuntimeError(
                "Keine Chat-Service-URL konfiguriert. Setze die Umgebungsvariable "
                "RAG_CHAT_SERVICE_URL oder übergib einen Endpunkt an den "
                "ChatServiceResponder."
            )
        self._timeout = timeout
        self._api_key = api_key or os.environ.get("RAG_CHAT_SERVICE_TOKEN")

    def __call__(self, prompt: ChatPrompt) -> str:
        if not prompt.context:
            return self.NO_CONTEXT_MESSAGE

        payload = {
            "messages": [
                {"role": message.role, "content": message.content}
                for message in prompt.messages
            ],
            "context": [self._normalise_context_item(item) for item in prompt.context],
        }

        request = urllib.request.Request(
            self._endpoint,
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json"},
        )
        if self._api_key:
            request.add_header("Authorization", f"Bearer {self._api_key}")

        try:
            with urllib.request.urlopen(request, timeout=self._timeout) as response:
                body = response.read()
        except urllib.error.URLError as exc:  # pragma: no cover - Netzwerkausfälle sind schwer zu testen
            raise RuntimeError(f"Chat-Service nicht erreichbar: {exc}") from exc

        try:
            data = json.loads(body.decode("utf-8"))
        except json.JSONDecodeError as exc:  # pragma: no cover - Netzwerkdaten schwer zu simulieren
            raise RuntimeError("Ungültige Antwort vom Chat-Service erhalten.") from exc

        answer = self._extract_answer(data)
        if not answer:
            raise RuntimeError("Der Chat-Service hat keine Antwort geliefert.")
        return answer.strip()

    @staticmethod
    def _normalise_context_item(item: Any) -> Dict[str, Any]:
        return {
            "id": getattr(item, "chunk_id", ""),
            "text": getattr(item, "text", ""),
            "score": getattr(item, "score", 0.0),
            "metadata": getattr(item, "metadata", {}),
        }

    @staticmethod
    def _extract_answer(payload: Any) -> Optional[str]:
        if isinstance(payload, dict):
            answer = payload.get("answer")
            if isinstance(answer, str) and answer.strip():
                return answer
            message = payload.get("message")
            if isinstance(message, dict):
                content = message.get("content")
                if isinstance(content, str) and content.strip():
                    return content
            choices = payload.get("choices")
            if isinstance(choices, list):
                for choice in choices:
                    if not isinstance(choice, dict):
                        continue
                    message = choice.get("message")
                    if isinstance(message, dict):
                        content = message.get("content")
                        if isinstance(content, str) and content.strip():
                            return content
                    text = choice.get("text")
                    if isinstance(text, str) and text.strip():
                        return text
        return None


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Interaktive Konsole für den RAG-Chatbot")
    parser.add_argument(
        "--index",
        type=Path,
        default=Path("data/rag-chatbot/index.json"),
        help="Pfad zum zuvor erzeugten semantischen Index",
    )
    parser.add_argument("--top-k", type=int, default=3, help="Anzahl der Kontext-Chunks pro Frage")
    parser.add_argument(
        "--min-score",
        type=float,
        default=0.2,
        help="Minimale Ähnlichkeit, damit ein Chunk berücksichtigt wird",
    )
    parser.add_argument(
        "--history-limit",
        type=int,
        default=4,
        help="Anzahl der vorangegangenen Benutzer/Assistenten-Paare, die behalten werden",
    )
    parser.add_argument(
        "--chat-url",
        type=str,
        default=None,
        help=(
            "HTTP-Endpunkt des Chat-Services. Standard: Wert der Umgebungsvariable "
            "RAG_CHAT_SERVICE_URL."
        ),
    )
    parser.add_argument(
        "--chat-timeout",
        type=float,
        default=30.0,
        help="Zeitlimit für Anfragen an den Chat-Service (in Sekunden)",
    )
    parser.add_argument(
        "--chat-token",
        type=str,
        default=None,
        help=(
            "Optionales API-Token für den Chat-Service. Standard: Wert der "
            "Umgebungsvariable RAG_CHAT_SERVICE_TOKEN."
        ),
    )
    return parser


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()

    index = SemanticIndex(args.index)
    try:
        responder = ChatServiceResponder(
            endpoint=args.chat_url,
            timeout=args.chat_timeout,
            api_key=args.chat_token,
        )
    except RuntimeError as exc:
        print(f"Fehler: {exc}", file=sys.stderr)
        return
    session = ChatSession(
        index,
        responder=responder,
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

        try:
            turn = session.send(user_input)
        except RuntimeError as exc:
            print(f"Fehler beim Abruf der Antwort: {exc}")
            continue

        print(_format_turn(turn))


def _format_turn(turn: ChatTurn) -> str:
    return f"Bot: {turn.response}"


if __name__ == "__main__":
    main()
