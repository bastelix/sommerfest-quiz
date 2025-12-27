"""Minimal HTTP chat relay for QuizRace RAG chatbot.

This service accepts the same payload as :class:`HttpChatResponder` sends from
our Symfony application.  It validates the bearer token, enriches the prompt
with the retrieved context chunks and forwards the request to OpenAI's chat
completions API.  The result is returned in a format that
``HttpChatResponder->respond()`` understands.
"""
from __future__ import annotations

import json
import logging
import os
from typing import Any, Callable, Dict, List, Optional, Tuple

from fastapi import Depends, FastAPI, Header, HTTPException, status
from fastapi.responses import JSONResponse
from openai import APIConnectionError, APIError, APITimeoutError, AuthenticationError, BadRequestError
from openai import OpenAI
from pydantic import BaseModel, Field, validator

LOGGER = logging.getLogger(__name__)

app = FastAPI(title="QuizRace RAG Chat Service")


class ChatMessage(BaseModel):
    role: str = Field(..., description="Role of the speaker, e.g. system/user/assistant")
    content: str = Field(..., description="Natural language message content")

    @validator("role", "content")
    def _strip(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("value must not be empty")
        return value


class ContextItem(BaseModel):
    id: Optional[str] = Field(default=None)
    text: Optional[str] = Field(default=None)
    score: Optional[float] = Field(default=None)
    metadata: Optional[Dict[str, Any]] = Field(default=None)

    class Config:
        extra = "allow"


class ChatRequest(BaseModel):
    messages: List[ChatMessage]
    context: List[ContextItem] = Field(default_factory=list)


def require_authorisation(authorization: Optional[str] = Header(default=None)) -> None:
    expected_token = os.environ.get("RAG_CHAT_SERVICE_TOKEN")
    if not expected_token:
        return

    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Missing bearer token")

    prefix = "Bearer "
    supplied = authorization[len(prefix):].strip() if authorization.startswith(prefix) else authorization.strip()
    if supplied != expected_token:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Invalid bearer token")


def _load_float_option(env_key: str) -> Optional[float]:
    raw = os.environ.get(env_key)
    if raw is None or raw.strip() == "":
        return None
    try:
        return float(raw)
    except ValueError as exc:  # pragma: no cover - defensive branch
        LOGGER.warning("Invalid numeric value for %s: %s", env_key, raw, exc_info=exc)
        return None


def _load_int_option(env_key: str) -> Optional[int]:
    raw = os.environ.get(env_key)
    if raw is None or raw.strip() == "":
        return None
    try:
        return int(float(raw))
    except ValueError as exc:  # pragma: no cover - defensive branch
        LOGGER.warning("Invalid integer value for %s: %s", env_key, raw, exc_info=exc)
        return None


OPTION_MAP: Dict[str, Tuple[str, Callable[[str], Optional[Any]]]] = {
    "RAG_CHAT_SERVICE_TEMPERATURE": ("temperature", _load_float_option),
    "RAG_CHAT_SERVICE_TOP_P": ("top_p", _load_float_option),
    "RAG_CHAT_SERVICE_PRESENCE_PENALTY": ("presence_penalty", _load_float_option),
    "RAG_CHAT_SERVICE_FREQUENCY_PENALTY": ("frequency_penalty", _load_float_option),
    "RAG_CHAT_SERVICE_MAX_COMPLETION_TOKENS": ("max_completion_tokens", _load_int_option),
}


def _build_openai_options() -> Dict[str, Any]:
    options: Dict[str, Any] = {}
    for env_key, (payload_key, loader) in OPTION_MAP.items():
        value = loader(env_key)
        if value is not None:
            options[payload_key] = value
    return options


def _build_context_message(context: List[ContextItem]) -> Optional[Dict[str, str]]:
    if not context:
        return None

    lines: List[str] = []
    for index, item in enumerate(context, start=1):
        if not item.text:
            continue
        prefix_bits: List[str] = []
        if item.id:
            prefix_bits.append(f"ID {item.id}")
        if item.score is not None:
            prefix_bits.append(f"Score {item.score:.3f}")
        metadata_str = ""
        if item.metadata:
            try:
                metadata_str = json.dumps(item.metadata, ensure_ascii=False)
            except (TypeError, ValueError):  # pragma: no cover - metadata may be unserialisable
                metadata_str = str(item.metadata)
        heading = ", ".join(prefix_bits) if prefix_bits else f"Chunk {index}"
        body = item.text.strip()
        if metadata_str:
            lines.append(f"[{heading}] {body}\nMetadaten: {metadata_str}")
        else:
            lines.append(f"[{heading}] {body}")

    if not lines:
        return None

    context_block = "\n\n".join(lines)
    instructions = (
        "Nutze ausschließlich die folgenden Kontextinformationen, um die Frage der Nutzerin zu "
        "beantworten. Wenn die Daten nicht ausreichen, gib an, dass du es nicht weißt."
    )
    content = f"{instructions}\n\nKontext:\n{context_block}"
    return {"role": "system", "content": content}


def _augment_messages(messages: List[ChatMessage], context_message: Optional[Dict[str, str]]) -> List[Dict[str, str]]:
    augmented: List[Dict[str, str]] = []
    if context_message:
        augmented.append(context_message)
    augmented.extend({"role": msg.role, "content": msg.content} for msg in messages)
    return augmented


def _get_openai_client() -> OpenAI:
    api_key = os.environ.get("OPENAI_API_KEY")
    if not api_key:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="OPENAI_API_KEY not configured")
    return OpenAI(api_key=api_key)


@app.post("/chat", response_class=JSONResponse)
async def create_chat_completion(
    request: ChatRequest,
    _: None = Depends(require_authorisation),
) -> JSONResponse:
    if not request.messages:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="messages must not be empty")

    context_message = _build_context_message(request.context)
    payload_messages = _augment_messages(request.messages, context_message)

    model = os.environ.get("RAG_CHAT_SERVICE_MODEL", "gpt-4o-mini")
    options = _build_openai_options()

    client = _get_openai_client()
    try:
        completion = client.chat.completions.create(model=model, messages=payload_messages, **options)
    except (AuthenticationError, BadRequestError) as exc:
        LOGGER.error("OpenAI rejected the request: %s", exc)
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(exc)) from exc
    except (APITimeoutError, APIConnectionError) as exc:
        LOGGER.error("OpenAI request failed due to network/timeout: %s", exc)
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail="OpenAI request failed") from exc
    except APIError as exc:
        LOGGER.error("OpenAI API error: %s", exc)
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail="OpenAI API error") from exc

    answer: Optional[str] = None
    if completion.choices:
        first = completion.choices[0]
        if first.message and getattr(first.message, "content", None):
            answer = first.message.content
        elif getattr(first, "text", None):
            answer = first.text

    if not answer:
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail="OpenAI did not return a message")

    return JSONResponse({"answer": answer.strip()})


@app.get("/healthz", response_class=JSONResponse)
async def health() -> JSONResponse:
    return JSONResponse({"status": "ok"})


if __name__ == "__main__":
    import uvicorn

    host = os.environ.get("RAG_CHAT_SERVICE_HOST", "0.0.0.0")
    port = int(os.environ.get("RAG_CHAT_SERVICE_PORT", "8000"))
    uvicorn.run("scripts.openai_chat_service:app", host=host, port=port, reload=False)
