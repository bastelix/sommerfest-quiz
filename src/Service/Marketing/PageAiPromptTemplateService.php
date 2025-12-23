<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Infrastructure\Database;
use PDO;
use PDOException;

use function array_filter;
use function array_values;
use function file_get_contents;
use function is_array;
use function is_readable;
use function is_string;
use function json_decode;
use function trim;

final class PageAiPromptTemplateService
{
    /**
     * @var array<int, array{id:string,label:string,template:string}>|null
     */
    private ?array $cache = null;

    private string $templatePath;

    private PDO $pdo;

    public function __construct(?string $templatePath = null, ?PDO $pdo = null)
    {
        $this->templatePath = $templatePath ?? dirname(__DIR__, 3) . '/data/ai_prompt_templates.json';
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @return array<int, array{id:string,label:string,template:string}>
     */
    public function list(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $entries = $this->loadTemplates();
        $this->cache = $entries;

        return $entries;
    }

    /**
     * @return array{id:string,label:string,template:string}|null
     */
    public function findById(string $templateId): ?array
    {
        $candidate = trim($templateId);
        if ($candidate === '') {
            return null;
        }

        foreach ($this->list() as $template) {
            if ($template['id'] === $candidate) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id:string,label:string,template:string}>
     */
    private function loadTemplates(): array
    {
        $dbTemplates = $this->loadTemplatesFromDatabase();
        if ($dbTemplates === null) {
            return $this->loadTemplatesFromFile();
        }

        if ($dbTemplates !== []) {
            return $dbTemplates;
        }

        $fileTemplates = $this->loadTemplatesFromFile();
        if ($fileTemplates === []) {
            return [];
        }

        $this->seedDatabaseTemplates($fileTemplates);

        return $fileTemplates;
    }

    /**
     * @return array<int, array{id:string,label:string,template:string}>|null
     */
    private function loadTemplatesFromDatabase(): ?array
    {
        try {
            $stmt = $this->pdo->query('SELECT id, label, template FROM marketing_ai_prompts ORDER BY id');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException) {
            return null;
        }

        $templates = array_filter(array_map([$this, 'normalizeEntry'], $rows));

        return array_values($templates);
    }

    /**
     * @return array<int, array{id:string,label:string,template:string}>
     */
    private function loadTemplatesFromFile(): array
    {
        if (!is_readable($this->templatePath)) {
            return [];
        }

        $raw = file_get_contents($this->templatePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $templates = array_filter(array_map([$this, 'normalizeEntry'], $decoded));

        return array_values($templates);
    }

    /**
     * @param array<int, array{id:string,label:string,template:string}> $templates
     */
    private function seedDatabaseTemplates(array $templates): void
    {
        try {
            $this->pdo->beginTransaction();
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $sql = 'INSERT INTO marketing_ai_prompts (id, label, template) VALUES (?, ?, ?) '
                    . 'ON CONFLICT (id) DO UPDATE SET label = EXCLUDED.label, template = EXCLUDED.template, '
                    . 'updated_at = CURRENT_TIMESTAMP';
            } else {
                $sql = 'INSERT OR REPLACE INTO marketing_ai_prompts (id, label, template) VALUES (?, ?, ?)';
            }

            $stmt = $this->pdo->prepare($sql);
            foreach ($templates as $template) {
                $stmt->execute([$template['id'], $template['label'], $template['template']]);
            }

            $this->pdo->commit();
        } catch (PDOException) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    /**
     * @param mixed $entry
     * @return array{id:string,label:string,template:string}|null
     */
    private function normalizeEntry(mixed $entry): ?array
    {
        if (!is_array($entry)) {
            return null;
        }

        $id = $entry['id'] ?? '';
        $label = $entry['label'] ?? '';
        $template = $entry['template'] ?? '';

        if (!is_string($id) || !is_string($label) || !is_string($template)) {
            return null;
        }

        $id = trim($id);
        $label = trim($label);
        $template = trim($template);

        if ($id === '' || $label === '' || $template === '') {
            return null;
        }

        return [
            'id' => $id,
            'label' => $label,
            'template' => $template,
        ];
    }
}
