<?php

declare(strict_types=1);

namespace App\Service\Mcp;

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
    }
}
