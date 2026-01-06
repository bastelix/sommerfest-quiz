<?php

declare(strict_types=1);

namespace App\Service;

use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function is_readable;
use function json_decode;
use function pathinfo;
use function rtrim;
use function strtolower;
use const PATHINFO_FILENAME;

/**
 * Load namespace design presets from the content/design directory.
 */
class NamespaceDesignFileRepository
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    public function listNamespaces(): array
    {
        $designDir = $this->getDesignDirectory();
        $files = glob($designDir . '/*.json') ?: [];
        $namespaces = [];

        foreach ($files as $file) {
            $name = pathinfo((string) $file, PATHINFO_FILENAME);
            if ($name === '' || $name === '.') {
                continue;
            }

            $namespaces[] = strtolower($name);
        }

        sort($namespaces);

        return $namespaces;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadConfig(string $namespace): array
    {
        $data = $this->loadFile($namespace);
        $config = $data['config'] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadTokens(string $namespace): array
    {
        $data = $this->loadFile($namespace);
        $tokens = $data['tokens'] ?? $data['designTokens'] ?? [];

        return is_array($tokens) ? $tokens : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadEffects(string $namespace): array
    {
        $data = $this->loadFile($namespace);
        $effects = $data['effects'] ?? [];

        return is_array($effects) ? $effects : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFile(string $namespace): array
    {
        $path = $this->getDesignDirectory() . '/' . basename($namespace) . '.json';
        if (!is_readable($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function getDesignDirectory(): string
    {
        return rtrim($this->projectRoot, '/') . '/content/design';
    }
}
