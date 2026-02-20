<?php

declare(strict_types=1);

namespace App\Domain;

enum Plan: string
{
    case FREE = 'free';
    case STARTER = 'starter';
    case STANDARD = 'standard';

    /**
     * @return array<string,int>
     */
    public function limits(): array
    {
        return match ($this) {
            self::FREE => [
                'events' => 1,
                'teams' => 3,
                'catalogs' => 2,
                'questions' => 10,
                'pages' => 1,
                'wiki_entries' => 2,
                'news_articles' => 1,
                'chatbots' => 0,
                'custom_domains' => 0,
                'namespace_users' => 1,
                'storage_mb' => 50,
                'ai_generations_month' => 0,
            ],
            self::STARTER => [
                'events' => 3,
                'teams' => 30,
                'catalogs' => 15,
                'questions' => 100,
                'pages' => 5,
                'wiki_entries' => 10,
                'news_articles' => 5,
                'chatbots' => 1,
                'custom_domains' => 0,
                'namespace_users' => 2,
                'storage_mb' => 500,
                'ai_generations_month' => 0,
            ],
            self::STANDARD => [
                'events' => 50,
                'teams' => 200,
                'catalogs' => 50,
                'questions' => 500,
                'pages' => 100,
                'wiki_entries' => 100,
                'news_articles' => 100,
                'chatbots' => 10,
                'custom_domains' => 3,
                'namespace_users' => 10,
                'storage_mb' => 5000,
                'ai_generations_month' => 100,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function allMetrics(): array
    {
        return [
            'events',
            'teams',
            'catalogs',
            'questions',
            'pages',
            'wiki_entries',
            'news_articles',
            'chatbots',
            'custom_domains',
            'namespace_users',
            'storage_mb',
            'ai_generations_month',
        ];
    }
}
