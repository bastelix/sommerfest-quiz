<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

class PromptTemplateService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return list<array{id:int,name:string,prompt:string}>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, prompt FROM prompt_templates ORDER BY name ASC, id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'prompt' => (string) $row['prompt'],
            ],
            $rows
        );
    }

    /**
     * @return array{id:int,name:string,prompt:string}
     */
    public function update(int $id, string $name, string $prompt): array
    {
        $name = trim($name);
        $prompt = trim($prompt);

        if ($name === '' || $prompt === '') {
            throw new RuntimeException('invalid_payload');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE prompt_templates SET name = :name, prompt = :prompt, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'prompt' => $prompt,
        ]);

        if ($stmt->rowCount() === 0) {
            $exists = $this->pdo->prepare('SELECT id FROM prompt_templates WHERE id = :id');
            $exists->execute(['id' => $id]);
            if ($exists->fetch(PDO::FETCH_ASSOC) === false) {
                throw new RuntimeException('not_found');
            }
        }

        $fetch = $this->pdo->prepare('SELECT id, name, prompt FROM prompt_templates WHERE id = :id');
        $fetch->execute(['id' => $id]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PDOException('Unable to load prompt template.');
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'prompt' => (string) $row['prompt'],
        ];
    }
}
