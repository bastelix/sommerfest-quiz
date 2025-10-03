from __future__ import annotations

import json
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from rag_chatbot.chat import ChatMessage
from rag_chatbot.report import build_report, format_report, load_report, load_transcript, report_from_json
from rag_chatbot.transcript import ChatTranscript, TranscriptContext, TranscriptTurn


def sample_transcript() -> ChatTranscript:
    transcript = ChatTranscript()
    turn_one = TranscriptTurn(
        question="Was ist QuizRace?",
        response="Eine Event-Anwendung.",
        context=(
            TranscriptContext(
                chunk_id="chunk-1",
                score=0.42,
                text="QuizRace ist eine Web-Anwendung für Veranstaltungen.",
                metadata={"source": "docs/about.md"},
            ),
            TranscriptContext(
                chunk_id="chunk-2",
                score=0.37,
                text="Die Anwendung basiert auf Slim.",
                metadata={"title": "README"},
            ),
        ),
        prompt_messages=(
            ChatMessage("system", "system"),
            ChatMessage("user", "Was ist QuizRace?"),
            ChatMessage("assistant", "Eine Event-Anwendung."),
        ),
    )
    turn_two = TranscriptTurn(
        question="Welche Daten speichert QuizRace?",
        response="Es werden keine personenbezogenen Daten gespeichert.",
        context=(
            TranscriptContext(
                chunk_id="chunk-3",
                score=0.81,
                text="Keine personenbezogenen Daten werden erhoben.",
                metadata={"source": "docs/datenschutz.md"},
            ),
        ),
        prompt_messages=(
            ChatMessage("system", "system"),
            ChatMessage("user", "Welche Daten speichert QuizRace?"),
            ChatMessage("assistant", "Es werden keine personenbezogenen Daten gespeichert."),
        ),
    )
    transcript.extend([turn_one, turn_two])
    return transcript


def test_build_report_summarises_transcript() -> None:
    transcript = sample_transcript()
    report = build_report(transcript)

    assert report.stats.turns == 2
    assert report.stats.context_items == 3
    assert report.stats.unique_sources == 3

    top_sources = {source.source: source for source in report.sources}
    assert "docs/about.md" in top_sources
    assert top_sources["docs/about.md"].hits == 1
    assert top_sources["docs/about.md"].average_score == 0.42


def test_format_report_produces_human_readable_text() -> None:
    transcript = sample_transcript()
    report = build_report(transcript)

    output = format_report(report, top_k=2)
    assert "Auswertung des Gesprächsprotokolls" in output
    assert "Top-Quellen" in output
    assert "docs/datenschutz.md" in output


def test_transcript_roundtrip(tmp_path: Path) -> None:
    transcript = sample_transcript()
    path = tmp_path / "transcript.json"
    transcript.save(path)

    loaded = load_transcript(path)
    assert loaded.turns == transcript.turns

    report = load_report(path)
    assert report.stats.turns == 2


def test_report_from_json_accepts_dumped_transcript() -> None:
    transcript = sample_transcript()
    payload = transcript.to_dict()
    text = json.dumps(payload)

    report = report_from_json(text)
    assert report.stats.turns == 2
