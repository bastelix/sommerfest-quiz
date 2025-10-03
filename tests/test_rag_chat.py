from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import List

import pytest

import sys

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from rag_chatbot.chat import ChatSession
from rag_chatbot.retrieval import SearchResult


@dataclass
class FakeIndex:
    results: List[SearchResult]

    def search(self, query: str, *, top_k: int, min_score: float):
        return self.results[:top_k]


def test_chat_session_builds_prompt_with_context() -> None:
    results = [
        SearchResult(
            chunk_id="doc:0001",
            score=0.8,
            text="QuizRace ist eine Webanwendung für Quizrunden.",
            metadata={"title": "README", "chunk_index": 0},
        ),
        SearchResult(
            chunk_id="doc:0002",
            score=0.7,
            text="Sie nutzt Slim Framework und UIkit.",
            metadata={"title": "README", "chunk_index": 1},
        ),
    ]
    index = FakeIndex(results)
    captured: list = []

    def responder(prompt):
        captured.append(prompt)
        return "QuizRace ist eine Quiz-Plattform."  # noqa: D401

    session = ChatSession(index, responder=responder, top_k=2)
    turn = session.send("Was ist QuizRace?")

    assert turn.response == "QuizRace ist eine Quiz-Plattform."
    assert captured[0].context == tuple(results)
    messages = captured[0].messages
    assert messages[0].role == "system"
    assert messages[-1].content == "Was ist QuizRace?"
    assert messages[-2].role == "system"
    assert "Kontext aus der Wissensbasis" in messages[-2].content
    assert "README" in messages[-2].content


def test_chat_session_truncates_history() -> None:
    index = FakeIndex([])
    prompts: list = []
    responses = iter(["Antwort 1", "Antwort 2"])

    def responder(prompt):
        prompts.append(prompt)
        return next(responses)

    session = ChatSession(index, responder=responder, history_limit=1, top_k=1)

    session.send("Erste Frage")
    session.send("Zweite Frage")

    second_messages = prompts[1].messages
    assert second_messages[0].role == "system"
    assert second_messages[1].content == "Erste Frage"
    assert second_messages[2].content == "Antwort 1"
    assert second_messages[3].content == "Zweite Frage"


def test_chat_session_rejects_empty_input() -> None:
    index = FakeIndex([])

    def responder(prompt):
        return ""

    session = ChatSession(index, responder=responder)

    with pytest.raises(ValueError):
        session.send("   ")
