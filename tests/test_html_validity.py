import unittest
from html.parser import HTMLParser

class HTMLValidator(HTMLParser):
    VOID_TAGS = {
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    }

    def __init__(self):
        super().__init__()
        self.stack = []
        self.errors = []

    def handle_starttag(self, tag, attrs):
        if tag not in self.VOID_TAGS:
            self.stack.append(tag)

    def handle_endtag(self, tag):
        if not self.stack:
            self.errors.append(f"Unexpected closing tag: {tag}")
            return
        open_tag = self.stack.pop()
        if open_tag != tag:
            self.errors.append(f"Mismatched tag: expected {open_tag} but got {tag}")

    def close(self):
        super().close()
        if self.stack:
            self.errors.append('Unclosed tags: ' + ', '.join(self.stack))


def validate_html_file(path):
    parser = HTMLValidator()
    with open(path, 'r', encoding='utf-8') as f:
        for line in f:
            parser.feed(line)
    parser.close()
    return parser.errors


class TestHTMLValidity(unittest.TestCase):
    def test_index_html_is_valid(self):
        errors = validate_html_file('templates/index.twig')
        self.assertEqual(errors, [], msg='\n'.join(errors))

    def test_datenschutz_html_is_valid(self):
        errors = validate_html_file('templates/datenschutz.twig')
        self.assertEqual(errors, [], msg='\n'.join(errors))

    def test_help_html_is_valid(self):
        errors = validate_html_file('templates/help.twig')
        self.assertEqual(errors, [], msg='\n'.join(errors))

    def test_impressum_html_is_valid(self):
        errors = validate_html_file('templates/impressum.twig')
        self.assertEqual(errors, [], msg='\n'.join(errors))

    def test_lizenz_html_is_valid(self):
        errors = validate_html_file('templates/lizenz.twig')
        self.assertEqual(errors, [], msg='\n'.join(errors))


if __name__ == '__main__':
    unittest.main()
