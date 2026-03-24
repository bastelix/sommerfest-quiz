<?php

declare(strict_types=1);

namespace Tests\Service\Marketing\Wiki;

use App\Service\Marketing\Wiki\EditorJsToMarkdown;
use PHPUnit\Framework\TestCase;

final class EditorJsToMarkdownTest extends TestCase
{
    private EditorJsToMarkdown $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new EditorJsToMarkdown();
    }

    public function testConvertTableBlockWithHeadingsToMarkdown(): void
    {
        $payload = [
            'blocks' => [
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => true,
                        'content' => [
                            ['Befehl', 'Beschreibung'],
                            ['STD', 'Alias für ein Referenzinstrument'],
                            ['ACC', 'Systemgenauigkeit und Toleranz'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($payload);

        $this->assertStringContainsString('| Befehl | Beschreibung |', $result['markdown']);
        $this->assertStringContainsString('| --- | --- |', $result['markdown']);
        $this->assertStringContainsString('| STD | Alias für ein Referenzinstrument |', $result['markdown']);
    }

    public function testConvertTableBlockWithHeadingsToHtml(): void
    {
        $payload = [
            'blocks' => [
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => true,
                        'content' => [
                            ['Befehl', 'Beschreibung'],
                            ['STD', 'Alias für ein Referenzinstrument'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($payload);

        $this->assertStringContainsString('<thead>', $result['html']);
        $this->assertStringContainsString('<th>Befehl</th>', $result['html']);
        $this->assertStringContainsString('<tbody>', $result['html']);
        $this->assertStringContainsString('<td>STD</td>', $result['html']);
        // No border attribute or inline style
        $this->assertStringNotContainsString('border=', $result['html']);
        $this->assertStringNotContainsString('style=', $result['html']);
    }

    public function testConvertTableBlockWithoutHeadingsToHtml(): void
    {
        $payload = [
            'blocks' => [
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => false,
                        'content' => [
                            ['A', 'B'],
                            ['C', 'D'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($payload);

        $this->assertStringNotContainsString('<thead>', $result['html']);
        $this->assertStringContainsString('<tbody>', $result['html']);
        $this->assertStringContainsString('<td>A</td>', $result['html']);
    }

    public function testConvertTableBlockWithoutHeadingsToMarkdown(): void
    {
        $payload = [
            'blocks' => [
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => false,
                        'content' => [
                            ['A', 'B'],
                            ['C', 'D'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->converter->convert($payload);

        // No header separator row
        $this->assertStringNotContainsString('| --- |', $result['markdown']);
        $this->assertStringContainsString('| A | B |', $result['markdown']);
        $this->assertStringContainsString('| C | D |', $result['markdown']);
    }

    public function testToSearchTextWithTableHeadings(): void
    {
        $payload = [
            'blocks' => [
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => true,
                        'content' => [
                            ['Befehl', 'Beschreibung'],
                            ['STD', 'Alias für ein Referenzinstrument'],
                            ['ACC', 'Systemgenauigkeit und Toleranz'],
                        ],
                    ],
                ],
            ],
        ];

        $searchText = $this->converter->toSearchText($payload);

        $this->assertStringContainsString('Befehl: STD', $searchText);
        $this->assertStringContainsString('Beschreibung: Alias für ein Referenzinstrument', $searchText);
        $this->assertStringContainsString('Befehl: ACC', $searchText);

        // Each row should end with a period
        $lines = explode("\n", $searchText);
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $this->assertStringEndsWith('.', $line);
            }
        }

        // No pipe characters or separator lines
        $this->assertStringNotContainsString('|', $searchText);
        $this->assertStringNotContainsString('---', $searchText);
    }

    public function testToSearchTextWithTableWithoutHeadings(): void
    {
        $payload = [
            'blocks' => [
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => false,
                        'content' => [
                            ['A', 'B'],
                            ['C', 'D'],
                        ],
                    ],
                ],
            ],
        ];

        $searchText = $this->converter->toSearchText($payload);

        $this->assertStringContainsString('A', $searchText);
        $this->assertStringNotContainsString('|', $searchText);
    }

    public function testToSearchTextWithMixedBlocks(): void
    {
        $payload = [
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Intro text.']],
                [
                    'type' => 'table',
                    'data' => [
                        'withHeadings' => true,
                        'content' => [
                            ['Key', 'Value'],
                            ['name', 'test'],
                        ],
                    ],
                ],
                ['type' => 'paragraph', 'data' => ['text' => 'Outro text.']],
            ],
        ];

        $searchText = $this->converter->toSearchText($payload);

        $this->assertStringContainsString('Intro text.', $searchText);
        $this->assertStringContainsString('Key: name', $searchText);
        $this->assertStringContainsString('Value: test', $searchText);
        $this->assertStringContainsString('Outro text.', $searchText);
    }
}
