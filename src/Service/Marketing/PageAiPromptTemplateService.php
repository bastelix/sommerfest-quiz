<?php

declare(strict_types=1);

namespace App\Service\Marketing;

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

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?? dirname(__DIR__, 3) . '/data/ai_prompt_templates.json';
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
