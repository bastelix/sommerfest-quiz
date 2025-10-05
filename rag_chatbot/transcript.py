from __future__ import annotations

"""Werkzeuge zum Aufzeichnen und Auswerten von Chatverläufen."""

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterable, List, Mapping, Sequence, Set, Tuple

from .chat import ChatMessage, ChatTurn
from .retrieval import SearchResult


@dataclass(frozen=True)
class TranscriptContext:
    """Metadaten zu einem einzelnen Kontexttreffer."""

    chunk_id: str
    score: float
    text: str
    metadata: Dict[str, object]

    @classmethod
    def from_search_result(cls, result: SearchResult) -> "TranscriptContext":
        return cls(
            chunk_id=result.chunk_id,
            score=result.score,
            text=result.text,
            metadata=dict(result.metadata),
        )

    def to_dict(self) -> Dict[str, object]:
        return {
            "chunk_id": self.chunk_id,
            "score": self.score,
            "text": self.text,
            "metadata": self.metadata,
        }

    @classmethod
    def from_dict(cls, data: Mapping[str, object]) -> "TranscriptContext":
        try:
            chunk_id = str(data["chunk_id"])
            score = float(data["score"])
            text = str(data["text"])
        except KeyError as exc:  # pragma: no cover - defensive programming
            raise KeyError(f"Fehlender Schlüssel im Kontext: {exc}") from exc
        metadata_raw = data.get("metadata")
        if isinstance(metadata_raw, Mapping):
            metadata = dict(metadata_raw)
        else:
            metadata = {}
        return cls(chunk_id=chunk_id, score=score, text=text, metadata=metadata)


@dataclass(frozen=True)
class TranscriptTurn:
    """Ein protokollierter Frage-Antwort-Durchlauf."""

    question: str
    response: str
    context: Tuple[TranscriptContext, ...]
    prompt_messages: Tuple[ChatMessage, ...]

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

    def to_dict(self) -> Dict[str, object]:
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

    @classmethod
    def from_dict(cls, data: Mapping[str, object]) -> "TranscriptTurn":
        try:
            question = str(data["question"])
            response = str(data["response"])
        except KeyError as exc:  # pragma: no cover - defensive programming
            raise KeyError(f"Fehlender Schlüssel in TranscriptTurn: {exc}") from exc

        context_items: Sequence[Mapping[str, object]]
        raw_context = data.get("context", [])
        if isinstance(raw_context, Sequence):
            context_items = [item for item in raw_context if isinstance(item, Mapping)]
        else:
            context_items = []
        context = tuple(TranscriptContext.from_dict(item) for item in context_items)

        prompt_items: Sequence[Mapping[str, object]]
        raw_prompt = data.get("prompt", [])
        if isinstance(raw_prompt, Sequence):
            prompt_items = [item for item in raw_prompt if isinstance(item, Mapping)]
        else:
            prompt_items = []
        prompt_messages = tuple(
            ChatMessage(role=str(item.get("role", "")), content=str(item.get("content", "")))
            for item in prompt_items
        )

        return cls(
            question=question,
            response=response,
            context=context,
            prompt_messages=prompt_messages,
        )


@dataclass(frozen=True)
class TranscriptStats:
    """Aggregierte Kennzahlen über ein Gesprächsprotokoll."""

    turns: int
    context_items: int
    average_score: float
    unique_sources: int

    def to_dict(self) -> Dict[str, object]:
        return {
            "turns": self.turns,
            "context_items": self.context_items,
            "average_score": self.average_score,
            "unique_sources": self.unique_sources,
        }


class ChatTranscript:
    """Sammelt Chat-Durchläufe und speichert sie bei Bedarf als JSON."""

    def __init__(self) -> None:
        self._turns: List[TranscriptTurn] = []

    @property
    def turns(self) -> Tuple[TranscriptTurn, ...]:
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

        scores: List[float] = []
        sources: Set[str] = set()
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

    def to_dict(self, *, include_stats: bool = True) -> Dict[str, object]:
        payload: Dict[str, object] = {
            "turns": [turn.to_dict() for turn in self._turns],
        }
        if include_stats:
            payload["stats"] = self.stats().to_dict()
        return payload

    def save(self, path: Path, *, include_stats: bool = True) -> None:
        payload = self.to_dict(include_stats=include_stats)
        text = json.dumps(payload, ensure_ascii=False, indent=2)
        path.write_text(text + "\n", encoding="utf-8")

    @classmethod
    def from_dict(cls, data: Mapping[str, object]) -> "ChatTranscript":
        raw_turns = data.get("turns")
        if not isinstance(raw_turns, Sequence):
            raise ValueError("Ungültiges Transcript: 'turns' fehlt oder hat das falsche Format.")
        transcript = cls()
        transcript.extend(
            TranscriptTurn.from_dict(item)
            for item in raw_turns
            if isinstance(item, Mapping)
        )
        return transcript

    @classmethod
    def load(cls, path: Path) -> "ChatTranscript":
        data = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(data, Mapping):
            raise ValueError("Die Transcript-Datei enthält kein JSON-Objekt.")
        return cls.from_dict(data)


__all__ = [
    "ChatTranscript",
    "TranscriptContext",
    "TranscriptStats",
    "TranscriptTurn",
]
