from __future__ import annotations

"""Auswertung von Gesprächsprotokollen für den RAG-Chatbot."""

from dataclasses import dataclass
from pathlib import Path
from statistics import mean
from typing import Dict, List, Tuple
import json

from .transcript import ChatTranscript, TranscriptStats


@dataclass(frozen=True)
class SourceReport:
    """Kennzahlen für eine Quelle innerhalb eines Transkripts."""

    source: str
    hits: int
    average_score: float
    max_score: float

    def to_dict(self) -> Dict[str, object]:
        return {
            "source": self.source,
            "hits": self.hits,
            "average_score": self.average_score,
            "max_score": self.max_score,
        }


@dataclass(frozen=True)
class TranscriptReport:
    """Aggregierte Auswertung eines Gesprächsprotokolls."""

    stats: TranscriptStats
    sources: Tuple[SourceReport, ...]

    def to_dict(self) -> Dict[str, object]:
        return {
            "stats": self.stats.to_dict(),
            "sources": [source.to_dict() for source in self.sources],
        }


def build_report(transcript: ChatTranscript) -> TranscriptReport:
    """Erstellt einen Bericht mit Kennzahlen zu einem Transcript."""

    stats = transcript.stats()
    if stats.turns == 0:
        return TranscriptReport(stats=stats, sources=tuple())

    buckets: Dict[str, List[float]] = {}
    for turn in transcript.turns:
        for idx, context in enumerate(turn.context):
            source = str(context.metadata.get("source") or context.metadata.get("title") or context.chunk_id)
            buckets.setdefault(source, []).append(context.score)

    sources = tuple(
        SourceReport(
            source=source,
            hits=len(scores),
            average_score=round(mean(scores), 6),
            max_score=round(max(scores), 6),
        )
        for source, scores in sorted(
            buckets.items(),
            key=lambda item: (len(item[1]), mean(item[1])),
            reverse=True,
        )
    )

    return TranscriptReport(stats=stats, sources=sources)


def format_report(report: TranscriptReport, *, top_k: int = 5) -> str:
    """Formatiert den Bericht menschenlesbar."""

    stats = report.stats
    lines = [
        "Auswertung des Gesprächsprotokolls:",
        f"- Runden: {stats.turns}",
        f"- Kontexttreffer: {stats.context_items}",
        f"- Durchschnittlicher Score: {stats.average_score}",
        f"- Einzigartige Quellen: {stats.unique_sources}",
    ]

    if report.sources:
        lines.append("")
        lines.append("Top-Quellen:")
        for position, source in enumerate(report.sources[:top_k], start=1):
            lines.append(
                f"{position}. {source.source} – "
                f"Treffer: {source.hits}, ⌀ Score: {source.average_score}, Max: {source.max_score}"
            )
    else:
        lines.append("")
        lines.append("Keine Kontexte im Protokoll vorhanden.")

    return "\n".join(lines)


def load_transcript(path: Path) -> ChatTranscript:
    """Lädt ein gespeichertes Transcript von der Festplatte."""

    return ChatTranscript.load(path)


def load_report(path: Path) -> TranscriptReport:
    """Lädt ein Transcript und gibt dessen Bericht zurück."""

    transcript = load_transcript(path)
    return build_report(transcript)


def report_from_json(text: str) -> TranscriptReport:
    """Erstellt einen Bericht aus JSON-Text."""

    data = json.loads(text)
    if not isinstance(data, dict):  # pragma: no cover - defensive programming
        raise ValueError("Das JSON-Dokument muss ein Objekt enthalten.")
    transcript = ChatTranscript.from_dict(data)
    return build_report(transcript)


__all__ = [
    "SourceReport",
    "TranscriptReport",
    "build_report",
    "format_report",
    "load_transcript",
    "load_report",
    "report_from_json",
]
