from __future__ import annotations

import json
import math
import re
from collections import Counter
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Sequence


TOKEN_RE = re.compile(r"\b\w+\b", re.UNICODE)


@dataclass(frozen=True)
class IndexOptions:
    corpus_path: Path
    output_path: Path
    max_features: int | None = None
    min_term_length: int = 2


@dataclass(frozen=True)
class IndexResult:
    chunks: int
    vocabulary_size: int
    output_path: Path


def build_index(options: IndexOptions) -> IndexResult:
    chunks = list(_load_corpus(options.corpus_path))
    if not chunks:
        raise ValueError("Das Korpus ist leer – bitte zuerst die Wissensbasis erzeugen.")

    tokenised_texts = [_tokenise(chunk["text"]) for chunk in chunks]
    vocabulary = _build_vocabulary(
        tokenised_texts,
        max_features=options.max_features,
        min_term_length=options.min_term_length,
    )
    if not vocabulary:
        raise ValueError("Es konnten keine Terme für den Index extrahiert werden.")

    idf = _compute_idf(tokenised_texts, vocabulary)
    indexed_chunks = _vectorise_chunks(chunks, tokenised_texts, vocabulary, idf)

    payload = {
        "vocabulary": vocabulary,
        "idf": idf,
        "chunks": indexed_chunks,
    }

    options.output_path.parent.mkdir(parents=True, exist_ok=True)
    options.output_path.write_text(json.dumps(payload), encoding="utf-8")

    return IndexResult(
        chunks=len(indexed_chunks),
        vocabulary_size=len(vocabulary),
        output_path=options.output_path,
    )


def _load_corpus(path: Path) -> Iterable[dict[str, object]]:
    if not path.exists():
        raise FileNotFoundError(path)
    with path.open("r", encoding="utf-8") as handle:
        for line in handle:
            line = line.strip()
            if not line:
                continue
            yield json.loads(line)


def _tokenise(text: str) -> list[str]:
    tokens = [token.lower() for token in TOKEN_RE.findall(text)]
    return tokens


def _build_vocabulary(
    tokenised_texts: Sequence[Sequence[str]],
    *,
    max_features: int | None,
    min_term_length: int,
) -> list[str]:
    term_counts: Counter[str] = Counter()
    for tokens in tokenised_texts:
        filtered = [token for token in tokens if len(token) >= min_term_length]
        term_counts.update(filtered)

    if not term_counts:
        return []

    sorted_terms = sorted(
        term_counts.items(),
        key=lambda item: (-item[1], item[0]),
    )
    if max_features is not None:
        sorted_terms = sorted_terms[: max_features]
    return [term for term, _ in sorted_terms]


def _compute_idf(tokenised_texts: Sequence[Sequence[str]], vocabulary: Sequence[str]) -> list[float]:
    doc_freq: Counter[str] = Counter()
    for tokens in tokenised_texts:
        unique_tokens = {token for token in tokens if token in vocabulary}
        doc_freq.update(unique_tokens)

    total_docs = len(tokenised_texts)
    idf = []
    for term in vocabulary:
        df = doc_freq.get(term, 0)
        weight = math.log((1 + total_docs) / (1 + df)) + 1.0
        idf.append(round(weight, 6))
    return idf


def _vectorise_chunks(
    chunks: Sequence[dict[str, object]],
    tokenised_texts: Sequence[Sequence[str]],
    vocabulary: Sequence[str],
    idf: Sequence[float],
) -> list[dict[str, object]]:
    vocab_index = {term: index for index, term in enumerate(vocabulary)}
    indexed_chunks: list[dict[str, object]] = []

    for chunk, tokens in zip(chunks, tokenised_texts, strict=True):
        counts = Counter(token for token in tokens if token in vocab_index)
        total = sum(counts.values())
        vector: list[list[float]] = []
        norm_sq = 0.0
        if total > 0:
            for term, count in counts.items():
                index = vocab_index[term]
                tf = count / total
                weight = tf * idf[index]
                norm_sq += weight * weight
                vector.append([index, round(weight, 6)])
        vector.sort(key=lambda item: item[0])

        indexed_chunks.append(
            {
                "id": chunk["id"],
                "text": chunk["text"],
                "metadata": {
                    key: chunk[key]
                    for key in ("source", "title", "chunk_index", "word_count")
                    if key in chunk
                },
                "vector": vector,
                "norm": round(math.sqrt(norm_sq), 6),
            }
        )

    return indexed_chunks

