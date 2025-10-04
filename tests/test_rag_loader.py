from pathlib import Path

from rag_chatbot.loader import iter_source_files, load_documents


def test_iter_source_files_includes_txt(tmp_path: Path) -> None:
    docs_dir = tmp_path / "docs"
    docs_dir.mkdir()
    text_file = docs_dir / "notizen.txt"
    text_file.write_text("Ein Textdokument nur für Tests.", encoding="utf-8")

    files = list(iter_source_files([docs_dir]))

    assert text_file in files

    documents = load_documents([docs_dir])
    assert documents
    assert documents[0].path == text_file
    assert "Textdokument" in documents[0].text
    assert documents[0].source.endswith("notizen.txt")
