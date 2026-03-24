<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\ResultService;
use App\Service\TeamService;
use PDO;

final class QuizTools
{
    private EventService $events;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(private readonly PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->events = new EventService($pdo);
    }

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_events',
                'method' => 'listEvents',
                'description' => 'List all quiz events for a namespace. Returns uid, '
                    . 'slug, name, dates, published status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'get_event',
                'method' => 'getEvent',
                'description' => 'Get a single quiz event by its UID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                    ],
                    'required' => ['event_uid'],
                ],
            ],
            [
                'name' => 'list_catalogs',
                'method' => 'listCatalogs',
                'description' => 'List all question catalogs for a quiz event. Returns '
                    . 'uid, slug, name, description, and sort order.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                    ],
                    'required' => ['event_uid'],
                ],
            ],
            [
                'name' => 'get_catalog',
                'method' => 'getCatalog',
                'description' => 'Get a question catalog with all its questions. Each '
                    . 'question has type, prompt, options, answers, and more.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                        'slug' => ['type' => 'string', 'description' => 'Catalog slug'],
                    ],
                    'required' => ['event_uid', 'slug'],
                ],
            ],
            [
                'name' => 'upsert_catalog',
                'method' => 'upsertCatalog',
                'description' => 'Create or update a question catalog with its questions. '
                    . 'Provide slug and an array of question objects. Each question '
                    . 'needs at least type and prompt.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                        'slug' => ['type' => 'string', 'description' => 'Catalog slug'],
                        'name' => ['type' => 'string', 'description' => 'Optional catalog display name'],
                        'description' => ['type' => 'string', 'description' => 'Optional catalog description'],
                        'questions' => [
                            'type' => 'array',
                            'description' => 'Array of question objects with type, prompt, '
                                . 'options, answers, etc.',
                        ],
                    ],
                    'required' => ['event_uid', 'slug', 'questions'],
                ],
            ],
            [
                'name' => 'list_results',
                'method' => 'listResults',
                'description' => 'Get all quiz results for an event. Returns player name, '
                    . 'catalog, score, points, and timing data.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                    ],
                    'required' => ['event_uid'],
                ],
            ],
            [
                'name' => 'submit_result',
                'method' => 'submitResult',
                'description' => 'Submit a quiz result for a player. Requires player name, '
                    . 'catalog identifier, correct count, and total questions.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                        'name' => ['type' => 'string', 'description' => 'Player or team name'],
                        'catalog' => ['type' => 'string', 'description' => 'Catalog UID or slug'],
                        'correct' => ['type' => 'integer', 'description' => 'Number of correct answers'],
                        'total' => ['type' => 'integer', 'description' => 'Total number of questions answered'],
                        'wrong' => [
                            'type' => 'array',
                            'description' => 'Optional array of 1-based indices of wrong answers',
                        ],
                        'answers' => [
                            'type' => 'array',
                            'description' => 'Optional per-question answer details',
                        ],
                    ],
                    'required' => ['event_uid', 'name', 'catalog', 'correct', 'total'],
                ],
            ],
            [
                'name' => 'list_teams',
                'method' => 'listTeams',
                'description' => 'List all teams for a quiz event.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'event_uid' => ['type' => 'string', 'description' => 'The event UID'],
                    ],
                    'required' => ['event_uid'],
                ],
            ],
        ];
    }

    // ── Tool Handlers ──────────────────────────

    public function listEvents(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $all = $this->events->getAll($ns);

        $items = [];
        foreach ($all as $event) {
            $items[] = [
                'uid' => $event['uid'],
                'slug' => $event['slug'],
                'name' => $event['name'],
                'start_date' => $event['start_date'] ?? null,
                'end_date' => $event['end_date'] ?? null,
                'description' => $event['description'] ?? null,
                'published' => $event['published'],
            ];
        }

        return ['namespace' => $ns, 'events' => $items];
    }

    public function getEvent(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');

        $event = $this->events->getByUidInNamespace($uid, $ns);
        if ($event === null) {
            throw new \InvalidArgumentException('event_not_found');
        }

        return ['namespace' => $ns, 'event' => $event];
    }

    public function listCatalogs(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');
        $this->assertEventInNamespace($uid, $ns);

        $catalogs = $this->makeCatalogService($uid);
        $raw = $catalogs->read('catalogs.json');
        $items = $raw !== null ? (json_decode($raw, true) ?? []) : [];

        return ['namespace' => $ns, 'event_uid' => $uid, 'catalogs' => $items];
    }

    public function getCatalog(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');
        $slug = $this->requireString($args, 'slug');
        $this->assertEventInNamespace($uid, $ns);

        $catalogs = $this->makeCatalogService($uid);
        $raw = $catalogs->read($slug . '.json');
        if ($raw === null) {
            throw new \InvalidArgumentException('catalog_not_found');
        }

        $questions = json_decode($raw, true) ?? [];

        return ['namespace' => $ns, 'event_uid' => $uid, 'slug' => $slug, 'questions' => $questions];
    }

    public function upsertCatalog(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');
        $slug = $this->requireString($args, 'slug');
        $this->assertEventInNamespace($uid, $ns);

        $questions = $args['questions'] ?? null;
        if (!is_array($questions)) {
            throw new \InvalidArgumentException('questions must be an array');
        }

        $catalogs = $this->makeCatalogService($uid);
        $catalogs->createCatalog($slug . '.json');
        $catalogs->write($slug . '.json', $questions);

        // Update metadata if provided
        if (isset($args['name']) || isset($args['description'])) {
            $raw = $catalogs->read('catalogs.json');
            $list = $raw !== null ? (json_decode($raw, true) ?? []) : [];
            foreach ($list as &$cat) {
                if (($cat['slug'] ?? '') === $slug) {
                    if (isset($args['name']) && is_string($args['name'])) {
                        $cat['name'] = trim($args['name']);
                    }
                    if (isset($args['description']) && is_string($args['description'])) {
                        $cat['description'] = trim($args['description']);
                    }
                    break;
                }
            }
            unset($cat);
            $catalogs->write('catalogs.json', $list);
        }

        return ['status' => 'ok', 'namespace' => $ns, 'event_uid' => $uid, 'slug' => $slug];
    }

    public function listResults(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');
        $this->assertEventInNamespace($uid, $ns);

        $results = new ResultService($this->pdo);

        return ['namespace' => $ns, 'event_uid' => $uid, 'results' => $results->getAll($uid)];
    }

    public function submitResult(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');
        $this->assertEventInNamespace($uid, $ns);

        $name = $this->requireString($args, 'name');
        $catalog = $this->requireString($args, 'catalog');

        $data = [
            'name' => $name,
            'catalog' => $catalog,
            'correct' => (int) ($args['correct'] ?? 0),
            'total' => (int) ($args['total'] ?? 0),
            'wrong' => isset($args['wrong']) && is_array($args['wrong']) ? $args['wrong'] : [],
            'answers' => isset($args['answers']) && is_array($args['answers']) ? $args['answers'] : [],
        ];

        $results = new ResultService($this->pdo);
        $entry = $results->add($data, $uid);

        return ['status' => 'ok', 'namespace' => $ns, 'event_uid' => $uid, 'result' => $entry];
    }

    public function listTeams(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $uid = $this->requireString($args, 'event_uid');
        $this->assertEventInNamespace($uid, $ns);

        $config = new ConfigService($this->pdo);
        $config->setActiveEventUid($uid);
        $teams = new TeamService($this->pdo, $config);

        return ['namespace' => $ns, 'event_uid' => $uid, 'teams' => $teams->getAllForEvent($uid)];
    }

    // ── Private Helpers ─────────────────────────

    private function requireString(array $args, string $key): string
    {
        $value = isset($args[$key]) && is_string($args[$key]) ? trim($args[$key]) : '';
        if ($value === '') {
            throw new \InvalidArgumentException($key . ' is required');
        }
        return $value;
    }

    private function assertEventInNamespace(string $uid, string $ns): void
    {
        if (!$this->events->belongsToNamespace($uid, $ns)) {
            throw new \InvalidArgumentException('event_not_found');
        }
    }

    private function makeCatalogService(string $eventUid): CatalogService
    {
        $config = new ConfigService($this->pdo);
        return new CatalogService($this->pdo, $config, null, '', $eventUid);
    }
}
