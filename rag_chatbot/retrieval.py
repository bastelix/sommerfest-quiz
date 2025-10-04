from __future__ import annotations

import json
import math
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable

from .index_builder import TOKEN_RE


@dataclass(frozen=True)
class SearchResult:
    chunk_id: str
    score: float
    text: str
    metadata: dict[str, Any]


class SemanticIndex:
    """Lädt einen semantischen Index und ermöglicht Ähnlichkeitssuchen."""

    def __init__(self, path: Path):
        if not path.exists():
            raise FileNotFoundError(path)
        payload = json.loads(path.read_text(encoding="utf-8"))
        self._vocabulary: list[str] = list(payload.get("vocabulary", []))
        self._idf: list[float] = list(payload.get("idf", []))
        self._term_to_index = {term: index for index, term in enumerate(self._vocabulary)}
        self._chunks = [
            _IndexedChunk.from_payload(item)
            for item in payload.get("chunks", [])
        ]

    @property
    def vocabulary(self) -> tuple[str, ...]:
        return tuple(self._vocabulary)

    def search(self, query: str, *, top_k: int = 5, min_score: float = 0.0) -> list[SearchResult]:
        query_vector = self._vectorise(query)
        if not query_vector:
            return []

        query_norm = math.sqrt(sum(weight * weight for weight in query_vector.values()))
        if query_norm == 0.0:
            return []

        results: list[SearchResult] = []
        for chunk in self._chunks:
            if chunk.norm == 0.0:
                continue
            score = chunk.dot(query_vector)
            if score <= 0.0:
                continue
            similarity = score / (chunk.norm * query_norm)
            if similarity >= min_score:
                results.append(
                    SearchResult(
                        chunk_id=chunk.chunk_id,
                        score=round(similarity, 6),
                        text=chunk.text,
                        metadata=dict(chunk.metadata),
                    )
                )

        results.sort(key=lambda item: item.score, reverse=True)
        return results[:top_k]

    def _vectorise(self, text: str) -> dict[int, float]:
        tokens = [token.lower() for token in TOKEN_RE.findall(text)]
        counts: dict[int, int] = {}
        for token in tokens:
            index = self._term_to_index.get(token)
            if index is None:
                continue
            counts[index] = counts.get(index, 0) + 1
        total = sum(counts.values())
        if total == 0:
            return {}
        vector: dict[int, float] = {}
        for index, count in counts.items():
            tf = count / total
            weight = tf * self._idf[index]
            vector[index] = weight
        return vector


@dataclass(frozen=True)
class _IndexedChunk:
    chunk_id: str
    text: str
    metadata: dict[str, Any]
    vector: dict[int, float]
    norm: float

    @classmethod
    def from_payload(cls, payload: dict[str, Any]) -> "_IndexedChunk":
        vector_pairs: Iterable[Iterable[float]] = payload.get("vector", [])
        vector: dict[int, float] = {}
        for pair in vector_pairs:
            if not isinstance(pair, list) or len(pair) != 2:
                continue
            index, weight = int(pair[0]), float(pair[1])
            vector[index] = weight

        return cls(
            chunk_id=str(payload.get("id", "")),
            text=str(payload.get("text", "")),
            metadata=dict(payload.get("metadata", {})),
            vector=vector,
            norm=float(payload.get("norm", 0.0)),
        )

    def dot(self, other: dict[int, float]) -> float:
        score = 0.0
        for index, weight in other.items():
            if index in self.vector:
                score += self.vector[index] * weight
        return score

