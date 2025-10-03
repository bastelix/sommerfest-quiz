#!/usr/bin/env python3
from __future__ import annotations

"""Erzeugt eine Auswertung für gespeicherte RAG-Transkripte."""

import argparse
import json
import sys
from pathlib import Path

from rag_chatbot.report import build_report, format_report, load_transcript

DEFAULT_TRANSCRIPT_PATH = Path("data/rag-chatbot/transcript.json")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Erstellt eine Zusammenfassung für ein RAG-Transcript.")
    parser.add_argument(
        "transcript",
        nargs="?",
        default=DEFAULT_TRANSCRIPT_PATH,
        type=Path,
        help="Pfad zur Transcript-JSON-Datei",
    )
    parser.add_argument("--top", type=int, default=5, help="Anzahl der aufzulistenden Top-Quellen")
    parser.add_argument(
        "--json",
        action="store_true",
        help="Bericht als JSON ausgeben",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()

    try:
        transcript = load_transcript(Path(args.transcript))
    except FileNotFoundError:
        print(f"Transcript-Datei {args.transcript} wurde nicht gefunden.", file=sys.stderr)
        return 1
    except ValueError as exc:
        print(f"Fehler beim Laden des Transcripts: {exc}", file=sys.stderr)
        return 1

    report = build_report(transcript)

    if args.json:
        payload = report.to_dict()
        print(json.dumps(payload, ensure_ascii=False, indent=2))
    else:
        print(format_report(report, top_k=args.top))

    return 0


if __name__ == "__main__":  # pragma: no cover - Skript Einstiegspunkt
    sys.exit(main())
