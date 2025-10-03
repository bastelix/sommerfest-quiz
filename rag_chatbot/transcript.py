from __future__ import annotations

"""Werkzeuge zum Aufzeichnen und Auswerten von Chatverläufen."""

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

from .chat import ChatMessage, ChatTurn
from .retrieval import SearchResult


@dataclass(frozen=True)
class TranscriptContext:
    """Metadaten zu einem einzelnen Kontexttreffer."""

    chunk_id: str
    score: float
    text: str
    metadata: dict[str, object]

    @classmethod
    def from_search_result(cls, result: SearchResult) -> "TranscriptContext":
        return cls(
            chunk_id=result.chunk_id,
            score=result.score,
            text=result.text,
            metadata=dict(result.metadata),
        )

    def to_dict(self) -> dict[str, object]:
        return {
            "chunk_id": self.chunk_id,
            "score": self.score,
            "text": self.text,
            "metadata": self.metadata,
        }


@dataclass(frozen=True)
class TranscriptTurn:
    """Ein protokollierter Frage-Antwort-Durchlauf."""

    question: str
    response: str
    context: tuple[TranscriptContext, ...]
    prompt_messages: tuple[ChatMessage, ...]

    @classmethod
    def from_prompt(cls, question: str, turn: ChatTurn) -> "TranscriptTurn":
        context = tuple(TranscriptContext.from_search_result(item) for item in turn.prompt.context)
        # Kopie der Prompt-Nachrichten, damit spätere Modifikationen nicht das Protokoll verändern.
        prompt_messages = tuple(
            ChatMessage(role=message.role, content=message.content)
            for message in turn.prompt.messages
        )
        return cls(
            question=question,
            response=turn.response,
            context=context,
            prompt_messages=prompt_messages,
        )

    def to_dict(self) -> dict[str, object]:
        return {
            "question": self.question,
            "response": self.response,
            "context": [item.to_dict() for item in self.context],
            "prompt": [
                {
                    "role": message.role,
                    "content": message.content,
                }
                for message in self.prompt_messages
            ],
        }


@dataclass(frozen=True)
class TranscriptStats:
    """Aggregierte Kennzahlen über ein Gesprächsprotokoll."""

    turns: int
    context_items: int
    average_score: float
    unique_sources: int

    def to_dict(self) -> dict[str, object]:
        return {
            "turns": self.turns,
            "context_items": self.context_items,
            "average_score": self.average_score,
            "unique_sources": self.unique_sources,
        }


class ChatTranscript:
    """Sammelt Chat-Durchläufe und speichert sie bei Bedarf als JSON."""

    def __init__(self) -> None:
        self._turns: list[TranscriptTurn] = []

    @property
    def turns(self) -> tuple[TranscriptTurn, ...]:
        return tuple(self._turns)

    def record(self, question: str, turn: ChatTurn) -> None:
        self._turns.append(TranscriptTurn.from_prompt(question, turn))

    def extend(self, items: Iterable[TranscriptTurn]) -> None:
        for item in items:
            self._turns.append(item)

    def clear(self) -> None:
        self._turns.clear()

    def stats(self) -> TranscriptStats:
        if not self._turns:
            return TranscriptStats(turns=0, context_items=0, average_score=0.0, unique_sources=0)

        scores: list[float] = []
        sources: set[str] = set()
        for turn in self._turns:
            for item in turn.context:
                scores.append(item.score)
                metadata = item.metadata
                source = str(
                    metadata.get("source")
                    or metadata.get("title")
                    or item.chunk_id
                )
                sources.add(source)

        average = sum(scores) / len(scores) if scores else 0.0
        return TranscriptStats(
            turns=len(self._turns),
            context_items=len(scores),
            average_score=round(average, 6),
            unique_sources=len(sources),
        )

    def to_dict(self, *, include_stats: bool = True) -> dict[str, object]:
        payload: dict[str, object] = {
            "turns": [turn.to_dict() for turn in self._turns],
        }
        if include_stats:
            payload["stats"] = self.stats().to_dict()
        return payload

    def save(self, path: Path, *, include_stats: bool = True) -> None:
        payload = self.to_dict(include_stats=include_stats)
        text = json.dumps(payload, ensure_ascii=False, indent=2)
        path.write_text(text + "\n", encoding="utf-8")


__all__ = [
    "ChatTranscript",
    "TranscriptContext",
    "TranscriptStats",
    "TranscriptTurn",
]
