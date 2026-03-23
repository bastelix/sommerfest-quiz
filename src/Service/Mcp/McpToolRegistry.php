<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\NamespaceService;
use App\Repository\NamespaceRepository;
use PDO;

final class McpToolRegistry
{
    /** @var array<string, array{handler: callable, definition: array}> */
    private array $tools = [];

    public function __construct(private readonly PDO $pdo, private readonly string $namespace)
    {
        $this->registerAll();
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array}>
     */
    public function listTools(): array
    {
        $out = [];
        foreach ($this->tools as $name => $entry) {
            $out[] = $entry['definition'];
        }
        return $out;
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    public function callTool(string $name, array $arguments): array
    {
        if (!isset($this->tools[$name])) {
            return [
                'content' => [['type' => 'text', 'text' => json_encode(['error' => 'unknown_tool', 'tool' => $name])]],
                'isError' => true,
            ];
        }

        try {
            $arguments = $this->decodeArguments($arguments);
            $result = ($this->tools[$name]['handler'])($arguments);
            return [
                'content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]],
            ];
        } catch (\Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => json_encode(['error' => $e->getMessage()])]],
                'isError' => true,
            ];
        }
    }

    private function registerAll(): void
    {
        // Built-in: list_namespaces
        $this->tools['list_namespaces'] = [
            'handler' => [$this, 'listNamespaces'],
            'definition' => [
                'name' => 'list_namespaces',
                'description' => 'List all available namespaces. Use this to discover which namespaces exist before querying pages, menus, or news.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
        ];

        $pageTools = new PageTools($this->pdo, $this->namespace);
        foreach ($pageTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$pageTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $menuTools = new MenuTools($this->pdo, $this->namespace);
        foreach ($menuTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$menuTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $newsTools = new NewsTools($this->pdo, $this->namespace);
        foreach ($newsTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$newsTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $footerTools = new FooterTools($this->pdo, $this->namespace);
        foreach ($footerTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$footerTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $quizTools = new QuizTools($this->pdo, $this->namespace);
        foreach ($quizTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$quizTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $backupTools = new BackupTools($this->namespace, $this->pdo);
        foreach ($backupTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$backupTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $stylesheetTools = new StylesheetTools($this->pdo, $this->namespace);
        foreach ($stylesheetTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$stylesheetTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }

        $wikiTools = new WikiTools($this->pdo, $this->namespace);
        foreach ($wikiTools->definitions() as $def) {
            $this->tools[$def['name']] = [
                'handler' => [$wikiTools, $def['method']],
                'definition' => [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'inputSchema' => $def['inputSchema'],
                ],
            ];
        }
    }

    /**
     * Recursively decode JSON Unicode escape sequences in all string values.
     */
    private function decodeArguments(mixed $data): mixed
    {
        if (is_string($data)) {
            return $this->decodeUnicodeEscapes($data);
        }

        if (is_array($data)) {
            return array_map(fn(mixed $item): mixed => $this->decodeArguments($item), $data);
        }

        return $data;
    }

    /**
     * Decode literal \uXXXX sequences that survived JSON parsing (double-encoded).
     */
    private function decodeUnicodeEscapes(string $value): string
    {
        if (strpos($value, '\\u') === false) {
            return $value;
        }

        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function (array $matches): string {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
        }, $value);
    }

    public function listNamespaces(array $args): array
    {
        $repo = new NamespaceRepository($this->pdo);
        $service = new NamespaceService($repo);
        $all = $service->allActive();

        $items = [];
        foreach ($all as $ns) {
            $items[] = [
                'namespace' => $ns['namespace'],
                'label' => $ns['label'] ?? null,
            ];
        }

        return ['tokenNamespace' => $this->namespace, 'namespaces' => $items];
    }
}
