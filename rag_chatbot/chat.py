from __future__ import annotations

"""Chat-spezifische Komponenten für den RAG-Chatbot."""

from dataclasses import dataclass
from typing import TYPE_CHECKING, List, Optional, Protocol, Sequence, Tuple

from .retrieval import SearchResult, SemanticIndex

if TYPE_CHECKING:
    from .transcript import ChatTranscript



ChatRole = str


@dataclass(frozen=True)
class ChatMessage:
    """Eine Chat-Nachricht mit Rolle und Inhalt."""

    role: ChatRole
    content: str


@dataclass(frozen=True)
class ChatPrompt:
    """Eingabestruktur für LLM-Aufrufe."""

    messages: Tuple[ChatMessage, ...]
    context: Tuple[SearchResult, ...]


@dataclass(frozen=True)
class ChatTurn:
    """Ergebnis eines Chat-Durchlaufs."""

    response: str
    prompt: ChatPrompt


class ChatResponder(Protocol):
    """Protokoll für Antwortgeneratoren."""

    def __call__(self, prompt: ChatPrompt) -> str:  # pragma: no cover - Signatur
        ...


DEFAULT_SYSTEM_PROMPT = (
    "Du bist ein hilfreicher Assistent für die QuizRace-Dokumentation. "
    "Beantworte Fragen ausschließlich anhand der bereitgestellten Kontexte."
)

DEFAULT_CONTEXT_HEADER = "Kontext aus der Wissensbasis:\n"


class ChatSession:
    """Verwaltet eine Konversation und baut Eingaben für ein Sprachmodell."""

    def __init__(
        self,
        index: SemanticIndex,
        responder: ChatResponder,
        *,
        system_prompt: str = DEFAULT_SYSTEM_PROMPT,
        history_limit: int = 4,
        top_k: int = 3,
        min_score: float = 0.2,
        transcript: Optional["ChatTranscript"] = None,
    ) -> None:
        if history_limit < 0:
            raise ValueError("history_limit darf nicht negativ sein.")
        if top_k <= 0:
            raise ValueError("top_k muss größer als 0 sein.")

        self._index = index
        self._responder = responder
        self._system_prompt = system_prompt.strip()
        self._history_limit = history_limit
        self._top_k = top_k
        self._min_score = min_score
        self._transcript = transcript
        self._history: List[ChatMessage] = []

    @property
    def history(self) -> Tuple[ChatMessage, ...]:
        """Gibt die bisherige Konversation ohne System-Prompt zurück."""

        return tuple(self._history)

    def send(self, user_message: str) -> ChatTurn:
        user_message = user_message.strip()
        if not user_message:
            raise ValueError("Die Nutzer-Nachricht darf nicht leer sein.")

        context = self._index.search(user_message, top_k=self._top_k, min_score=self._min_score)
        context_message = self._build_context_message(context)

        messages: List[ChatMessage] = [ChatMessage("system", self._system_prompt)]
        if self._history:
            messages.extend(self._history)
        if context_message:
            messages.append(context_message)
        messages.append(ChatMessage("user", user_message))

        prompt = ChatPrompt(messages=tuple(messages), context=tuple(context))
        response = self._responder(prompt).strip()

        turn = ChatTurn(response=response, prompt=prompt)

        self._history.extend((ChatMessage("user", user_message), ChatMessage("assistant", response)))
        self._truncate_history()

        if self._transcript is not None:
            self._transcript.record(user_message, turn)

        return turn

    def _truncate_history(self) -> None:
        if self._history_limit == 0:
            self._history.clear()
            return
        max_messages = self._history_limit * 2
        excess = len(self._history) - max_messages
        if excess > 0:
            del self._history[:excess]

    def _build_context_message(self, context: Sequence[SearchResult]) -> Optional[ChatMessage]:
        if not context:
            return None
        lines = [DEFAULT_CONTEXT_HEADER]
        for index, item in enumerate(context, start=1):
            summary = _summarise_text(item.text)
            source = _format_source(item)
            lines.append(f"[{index}] {source}\n{summary}")
        content = "\n\n".join(lines)
        return ChatMessage("system", content)


def _summarise_text(text: str, limit: int = 420) -> str:
    condensed = " ".join(text.split())
    if len(condensed) <= limit:
        return condensed
    return condensed[: limit - 1] + "…"


def _format_source(result: SearchResult) -> str:
    metadata = result.metadata
    title = metadata.get("title")
    chunk_index = metadata.get("chunk_index")
    if title:
        if chunk_index is not None:
            return f"{title} (Abschnitt {chunk_index})"
        return str(title)
    source = metadata.get("source")
    if source:
        return str(source)
    return result.chunk_id


__all__ = [
    "ChatMessage",
    "ChatPrompt",
    "ChatResponder",
    "ChatSession",
    "ChatTurn",
    "DEFAULT_SYSTEM_PROMPT",
]
