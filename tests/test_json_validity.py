import json
from pathlib import Path
import pytest


@pytest.mark.parametrize('p', list(Path('data/kataloge').glob('*.json')))
def test_catalog_files_are_valid_json(p):
    with p.open('r', encoding='utf-8') as f:
        json.load(f)
