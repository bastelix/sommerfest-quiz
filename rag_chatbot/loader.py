from __future__ import annotations

import re
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Iterator, Sequence


SUPPORTED_EXTENSIONS: Sequence[str] = (".md", ".markdown", ".html", ".htm")


@dataclass(frozen=True)
class Document:
    """Representation eines Quelldokuments."""

    path: Path
    text: str
    title: str

    @property
    def source(self) -> str:
        return str(self.path.as_posix())


_FRONTMATTER_RE = re.compile(r"^---\n.*?\n---\n", re.DOTALL)
_HEADING_RE = re.compile(r"^(#{1,6})\s*(.+)$", re.MULTILINE)
_CODEBLOCK_RE = re.compile(r"```.*?```", re.DOTALL)
_INLINE_CODE_RE = re.compile(r"`([^`]+)`")
_LINK_RE = re.compile(r"\[([^\]]+)\]\([^\)]+\)")
_IMAGE_RE = re.compile(r"!\[([^\]]*)\]\([^\)]+\)")
_HTML_TAG_RE = re.compile(r"<[^>]+>")
_MULTISPACE_RE = re.compile(r"[ \t]{2,}")
_NEWLINE_RE = re.compile(r"\n{3,}")
_LIST_MARKER_RE = re.compile(r"^\s*([*+-]|\d+\.)\s+", re.MULTILINE)


def _strip_frontmatter(text: str) -> str:
    return _FRONTMATTER_RE.sub("", text, count=1)


def _normalise_headings(text: str) -> str:
    def repl(match: re.Match[str]) -> str:
        level = len(match.group(1))
        heading = match.group(2).strip()
        return f"{heading}\n{'-' * max(level + 1, len(heading))}"

    return _HEADING_RE.sub(repl, text)


def _normalise_lists(text: str) -> str:
    return _LIST_MARKER_RE.sub("- ", text)


def _remove_codeblocks(text: str) -> str:
    text = _CODEBLOCK_RE.sub("", text)
    return _INLINE_CODE_RE.sub(r"\1", text)


def _unroll_links(text: str) -> str:
    text = _IMAGE_RE.sub(r"\1", text)
    return _LINK_RE.sub(r"\1", text)


def _strip_html(text: str) -> str:
    return _HTML_TAG_RE.sub("", text)


def _collapse_whitespace(text: str) -> str:
    text = _MULTISPACE_RE.sub(" ", text)
    text = _NEWLINE_RE.sub("\n\n", text)
    return text.strip()


def parse_document(path: Path) -> Document:
    raw = path.read_text(encoding="utf-8")
    text = _strip_frontmatter(raw)
    text = _remove_codeblocks(text)
    text = _unroll_links(text)
    text = _normalise_headings(text)
    text = _normalise_lists(text)
    text = _strip_html(text)
    text = _collapse_whitespace(text)

    title_match = re.search(r"^(.*)\n", text)
    title = title_match.group(1).strip() if title_match else path.stem
    return Document(path=path, text=text, title=title)


def iter_source_files(paths: Iterable[Path], extensions: Sequence[str] | None = None) -> Iterator[Path]:
    selected_exts = tuple(ext.lower() for ext in (extensions or SUPPORTED_EXTENSIONS))
    for root in paths:
        root = root.resolve()
        if root.is_file():
            if root.suffix.lower() in selected_exts:
                yield root
            continue
        if root.is_dir():
            for file in sorted(root.rglob("*")):
                if file.is_file() and file.suffix.lower() in selected_exts:
                    yield file


def load_documents(paths: Iterable[Path]) -> list[Document]:
    documents: list[Document] = []
    for path in iter_source_files(paths):
        try:
            documents.append(parse_document(path))
        except UnicodeDecodeError:
            continue
    return documents

