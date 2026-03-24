<?php

declare(strict_types=1);

namespace App\Service\Marketing\Wiki;

use InvalidArgumentException;

final class EditorJsToMarkdown
{
    /**
     * Convert the Editor.js payload into Markdown and HTML markup.
     *
     * @param array<string,mixed> $payload
     * @return array{markdown:string,html:string}
     */
    public function convert(array $payload): array
    {
        if (!isset($payload['blocks']) || !is_array($payload['blocks'])) {
            throw new InvalidArgumentException('Editor payload must contain "blocks".');
        }

        $markdownParts = [];
        $htmlParts = [];

        foreach ($payload['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = isset($block['type']) ? (string) $block['type'] : '';
            $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];

            switch ($type) {
                case 'header':
                    $level = isset($data['level']) ? (int) $data['level'] : 1;
                    $level = max(1, min(6, $level));
                    $text = $this->sanitizeInlineText($data['text'] ?? '');
                    $markdownParts[] = str_repeat('#', $level) . ' ' . $text;
                    $htmlParts[] = sprintf(
                        '<h%d>%s</h%d>',
                        $level,
                        $this->sanitizeInlineHtml($data['text'] ?? ''),
                        $level
                    );
                    break;
                case 'paragraph':
                    $text = $this->sanitizeInlineText($data['text'] ?? '');
                    $markdownParts[] = $text;
                    $htmlParts[] = sprintf('<p>%s</p>', $this->sanitizeInlineHtml($data['text'] ?? ''));
                    break;
                case 'list':
                    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
                    if ($items === []) {
                        break;
                    }
                    $style = isset($data['style']) && $data['style'] === 'ordered' ? 'ordered' : 'unordered';
                    $markdownParts[] = $this->renderListMarkdown($items, $style === 'ordered');
                    $htmlParts[] = $this->renderListHtml($items, $style === 'ordered');
                    break;
                case 'quote':
                    $text = $this->sanitizeInlineText($data['text'] ?? '');
                    $caption = $this->sanitizeInlineText($data['caption'] ?? '');
                    $markdown = '> ' . str_replace("\n", "\n> ", $text);
                    if ($caption !== '') {
                        $markdown .= "\n> — " . $caption;
                    }
                    $markdownParts[] = $markdown;
                    $html = sprintf(
                        '<blockquote><p>%s</p>%s</blockquote>',
                        $this->sanitizeInlineHtml($data['text'] ?? ''),
                        $caption !== '' ? sprintf('<footer>— %s</footer>', htmlspecialchars($caption, ENT_QUOTES)) : ''
                    );
                    $htmlParts[] = $html;
                    break;
                case 'code':
                    $code = (string) ($data['code'] ?? '');
                    $markdownParts[] = "```\n" . str_replace("```", "\u{FFFD}\u{FFFD}\u{FFFD}", $code) . "\n```";
                    $htmlParts[] = sprintf('<pre><code>%s</code></pre>', htmlspecialchars($code, ENT_QUOTES));
                    break;
                case 'table':
                    $rows = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];
                    if ($rows === []) {
                        break;
                    }
                    $withHeadings = isset($data['withHeadings']) && $data['withHeadings'] === true;
                    $markdownParts[] = $this->renderTableMarkdown($rows, $withHeadings);
                    $htmlParts[] = $this->renderTableHtml($rows, $withHeadings);
                    break;
                case 'warning':
                    $title = $this->sanitizeInlineText($data['title'] ?? '');
                    $message = $this->sanitizeInlineText($data['message'] ?? '');
                    $markdownParts[] = sprintf('> **%s**\n> %s', $title, $message);
                    $htmlParts[] = sprintf(
                        '<div class="wiki-callout"><strong>%s</strong><p>%s</p></div>',
                        htmlspecialchars($title, ENT_QUOTES),
                        htmlspecialchars($message, ENT_QUOTES)
                    );
                    break;
                case 'image':
                    $file = isset($data['file']) && is_array($data['file']) ? $data['file'] : [];
                    $url = isset($file['url']) ? (string) $file['url'] : '';
                    if ($url === '') {
                        break;
                    }
                    $caption = $this->sanitizeInlineText($data['caption'] ?? '');
                    $markdownParts[] = sprintf('![%s](%s)', $caption, $url);
                    $htmlParts[] = sprintf(
                        '<figure><img src="%s" alt="%s" loading="lazy">%s</figure>',
                        htmlspecialchars($url, ENT_QUOTES),
                        htmlspecialchars($caption, ENT_QUOTES),
                        $caption !== ''
                            ? sprintf('<figcaption>%s</figcaption>', htmlspecialchars($caption, ENT_QUOTES))
                            : ''
                    );
                    break;
                default:
                    $text = $this->sanitizeInlineText($data['text'] ?? '');
                    if ($text !== '') {
                        $markdownParts[] = $text;
                        $htmlParts[] = sprintf('<p>%s</p>', htmlspecialchars($text, ENT_QUOTES));
                    }
            }
        }

        $markdown = trim(implode(
            "\n\n",
            array_filter($markdownParts, static fn (string $part): bool => trim($part) !== '')
        ));
        $html = trim(implode(
            "\n",
            array_filter($htmlParts, static fn (string $part): bool => trim($part) !== '')
        ));

        return ['markdown' => $markdown, 'html' => $html];
    }

    /**
     * @param list<mixed> $items
     */
    private function renderListMarkdown(array $items, bool $ordered): string
    {
        $lines = [];
        $index = 1;
        foreach ($items as $item) {
            $text = $this->sanitizeInlineText(is_array($item) ? ($item['text'] ?? '') : $item);
            if ($text === '') {
                continue;
            }
            if ($ordered) {
                $lines[] = sprintf('%d. %s', $index, $text);
                $index++;
            } else {
                $lines[] = '- ' . $text;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<mixed> $items
     */
    private function renderListHtml(array $items, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $content = [];
        foreach ($items as $item) {
            $text = $this->sanitizeInlineHtml(is_array($item) ? ($item['text'] ?? '') : (string) $item);
            if ($text === '') {
                continue;
            }
            $content[] = sprintf('<li>%s</li>', $text);
        }

        if ($content === []) {
            return '';
        }

        return sprintf('<%1$s>%2$s</%1$s>', $tag, implode('', $content));
    }

    /**
     * @param list<mixed> $rows
     */
    private function renderTableMarkdown(array $rows, bool $withHeadings = true): string
    {
        $markdownRows = [];

        if ($withHeadings && $rows !== []) {
            $header = array_shift($rows);
            if (is_array($header)) {
                $headerText = array_map(fn ($cell): string => $this->sanitizeInlineText($cell), $header);
                $markdownRows[] = '| ' . implode(' | ', $headerText) . ' |';
                $markdownRows[] = '| ' . implode(' | ', array_fill(0, count($headerText), '---')) . ' |';
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $markdownRows[] = '| ' . implode(
                ' | ',
                array_map(fn ($cell): string => $this->sanitizeInlineText($cell), $row)
            ) . ' |';
        }

        return implode("\n", $markdownRows);
    }

    /**
     * @param list<mixed> $rows
     */
    private function renderTableHtml(array $rows, bool $withHeadings = true): string
    {
        if ($rows === []) {
            return '';
        }

        $thead = '';
        if ($withHeadings) {
            $header = array_shift($rows);
            if (is_array($header)) {
                $cells = array_map(
                    fn ($cell): string => sprintf('<th>%s</th>', $this->sanitizeInlineHtml($cell)),
                    $header
                );
                $thead = '<thead><tr>' . implode('', $cells) . '</tr></thead>';
            }
        }

        $bodyRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cells = array_map(
                fn ($cell): string => sprintf('<td>%s</td>', $this->sanitizeInlineHtml($cell)),
                $row
            );
            $bodyRows[] = '<tr>' . implode('', $cells) . '</tr>';
        }

        $tbody = $bodyRows === [] ? '' : '<tbody>' . implode('', $bodyRows) . '</tbody>';

        return sprintf('<table>%s%s</table>', $thead, $tbody);
    }

    /**
     * Convert the Editor.js payload into plaintext suitable for search indexing and RAG.
     *
     * Tables are serialized as "Header: Value – Header: Value." per row so that
     * semantic context is preserved even when a RAG chunk splits mid-table.
     *
     * @param array<string,mixed> $payload
     */
    public function toSearchText(array $payload): string
    {
        if (!isset($payload['blocks']) || !is_array($payload['blocks'])) {
            return '';
        }

        $parts = [];

        foreach ($payload['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = isset($block['type']) ? (string) $block['type'] : '';
            $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];

            switch ($type) {
                case 'table':
                    $rows = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];
                    if ($rows === []) {
                        break;
                    }
                    $withHeadings = isset($data['withHeadings']) && $data['withHeadings'] === true;
                    $parts[] = $this->renderTableSearchText($rows, $withHeadings);
                    break;
                default:
                    $text = $this->sanitizeInlineText($data['text'] ?? '');
                    if ($text === '' && isset($data['items']) && is_array($data['items'])) {
                        $itemTexts = [];
                        foreach ($data['items'] as $item) {
                            $itemText = $this->sanitizeInlineText(is_array($item) ? ($item['text'] ?? '') : $item);
                            if ($itemText !== '') {
                                $itemTexts[] = $itemText;
                            }
                        }
                        $text = implode('. ', $itemTexts);
                    }
                    if ($text === '' && isset($data['code'])) {
                        $text = (string) $data['code'];
                    }
                    if ($text !== '') {
                        $parts[] = $text;
                    }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param list<mixed> $rows
     */
    private function renderTableSearchText(array $rows, bool $withHeadings): string
    {
        if ($rows === []) {
            return '';
        }

        if (!$withHeadings) {
            $lines = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cells = array_map(fn ($cell): string => $this->sanitizeInlineText($cell), $row);
                $lines[] = implode(' – ', array_filter($cells, static fn (string $c): bool => $c !== '')) . '.';
            }

            return implode("\n", $lines);
        }

        $headers = array_shift($rows);
        if (!is_array($headers)) {
            return '';
        }

        $headerTexts = array_map(fn ($cell): string => $this->sanitizeInlineText($cell), $headers);
        $lines = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $pairs = [];
            foreach ($row as $colIndex => $cell) {
                $cellText = $this->sanitizeInlineText($cell);
                $headerText = $headerTexts[$colIndex] ?? '';
                if ($headerText !== '' && $cellText !== '') {
                    $pairs[] = $headerText . ': ' . $cellText;
                } elseif ($cellText !== '') {
                    $pairs[] = $cellText;
                }
            }

            if ($pairs !== []) {
                $lines[] = implode(' – ', $pairs) . '.';
            }
        }

        return implode("\n", $lines);
    }

    private function sanitizeInlineText(mixed $value): string
    {
        $text = is_string($value) ? $value : '';
        $text = strip_tags($text, '<b><strong><i><em><u><a><code><mark><del><s>');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return trim($text);
    }

    private function sanitizeInlineHtml(mixed $value): string
    {
        $text = is_string($value) ? $value : '';
        $text = strip_tags($text, '<b><strong><i><em><u><a><code><mark><br><span><del><s>');
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $text = str_replace(['javascript:', 'data:'], '', $text);

        return trim($text);
    }
}
