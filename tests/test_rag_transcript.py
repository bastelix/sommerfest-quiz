from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
import sys
from typing import List

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from rag_chatbot.chat import ChatSession
from rag_chatbot.retrieval import SearchResult
from rag_chatbot.transcript import ChatTranscript


@dataclass
class FakeIndex:
    results: List[SearchResult]

    def search(self, query: str, *, top_k: int, min_score: float):
        return self.results[:top_k]


def test_transcript_records_turn_and_stats() -> None:
    results = [
        SearchResult(
            chunk_id="doc:1",
            score=0.8,
            text="QuizRace ist eine Plattform für Quiz-Events.",
            metadata={"title": "README", "chunk_index": 0},
        ),
        SearchResult(
            chunk_id="doc:2",
            score=0.6,
            text="Die Anwendung verwendet Slim Framework.",
            metadata={"title": "README", "chunk_index": 1},
        ),
    ]
    transcript = ChatTranscript()

    session = ChatSession(
        FakeIndex(results),
        responder=lambda prompt: "Antwort aus der Wissensbasis.",
        top_k=2,
        transcript=transcript,
    )

    session.send("Was ist QuizRace?")

    assert len(transcript.turns) == 1
    turn = transcript.turns[0]
    assert turn.question == "Was ist QuizRace?"
    assert turn.response.startswith("Antwort aus der Wissensbasis")
    assert len(turn.context) == 2
    assert turn.context[0].metadata["title"] == "README"

    stats = transcript.stats()
    assert stats.turns == 1
    assert stats.context_items == 2
    assert stats.unique_sources == 1
    assert stats.average_score == 0.7


def test_transcript_save_creates_json(tmp_path: Path) -> None:
    results = [
        SearchResult(
            chunk_id="doc:42",
            score=0.9,
            text="QuizRace unterstützt Team-Wettbewerbe.",
            metadata={"source": "docs/features.md"},
        ),
    ]
    transcript = ChatTranscript()
    session = ChatSession(
        FakeIndex(results),
        responder=lambda prompt: "Antwort mit Kontext.",
        top_k=1,
        transcript=transcript,
    )

    session.send("Welche Wettbewerbe unterstützt QuizRace?")

    output = tmp_path / "transcript.json"
    transcript.save(output)

    payload = json.loads(output.read_text(encoding="utf-8"))
    assert "turns" in payload
    assert len(payload["turns"]) == 1
    assert payload["turns"][0]["question"].startswith("Welche Wettbewerbe")
    assert payload["stats"]["turns"] == 1
    assert payload["stats"]["unique_sources"] == 1
