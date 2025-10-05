from __future__ import annotations

from dataclasses import dataclass
from typing import Iterable, Iterator, List


@dataclass(frozen=True)
class Chunk:
    text: str
    word_count: int


def split_into_paragraphs(text: str) -> List[str]:
    paragraphs = [paragraph.strip() for paragraph in text.split("\n\n")]
    return [paragraph for paragraph in paragraphs if paragraph]


def chunk_paragraphs(
    paragraphs: Iterable[str],
    max_words: int = 180,
    overlap: int = 40,
) -> Iterator[Chunk]:
    buffer_words: List[str] = []
    for paragraph in paragraphs:
        words = paragraph.split()
        if not words:
            continue
        if not buffer_words:
            buffer_words.extend(words)
        elif len(buffer_words) + len(words) <= max_words:
            buffer_words.extend(words)
        else:
            yield from _flush_buffer(buffer_words, max_words, overlap)
            buffer_words.extend(words)
        while len(buffer_words) > max_words:
            yield from _flush_buffer(buffer_words, max_words, overlap)
    if buffer_words:
        yield Chunk(text=" ".join(buffer_words), word_count=len(buffer_words))


def _flush_buffer(buffer_words: List[str], max_words: int, overlap: int) -> Iterator[Chunk]:
    if not buffer_words:
        return
    chunk_words = buffer_words[:max_words]
    remainder = buffer_words[max_words:]
    yield Chunk(text=" ".join(chunk_words), word_count=len(chunk_words))
    buffer_words.clear()
    if overlap > 0:
        tail = chunk_words[-overlap:]
        buffer_words.extend(tail)
    buffer_words.extend(remainder)

