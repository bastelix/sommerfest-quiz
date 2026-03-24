<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NamespaceQuizController
{
    public const SCOPE_QUIZ_READ = 'quiz:read';
    public const SCOPE_QUIZ_WRITE = 'quiz:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
    ) {
    }

    // ── Events ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/namespaces/{ns}/events
     */
    public function listEvents(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $events = new EventService($pdo);

        $items = [];
        foreach ($events->getAll($ns) as $event) {
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

        return $this->json($response, ['namespace' => $ns, 'events' => $items]);
    }

    /**
     * GET /api/v1/namespaces/{ns}/events/{uid}
     */
    public function getEvent(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        return $this->json($response, ['namespace' => $ns, 'event' => $event]);
    }

    /**
     * POST /api/v1/namespaces/{ns}/events
     */
    public function createEvent(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $name = isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : '';
        if ($name === '') {
            return $this->json($response, ['error' => 'name_required'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $events = new EventService($pdo);

        $uid = bin2hex(random_bytes(16));
        $newEvent = [
            'uid' => $uid,
            'slug' => isset($payload['slug']) && is_string($payload['slug']) ? trim($payload['slug']) : $uid,
            'name' => $name,
            'start_date' => isset($payload['start_date']) && is_string($payload['start_date']) ? $payload['start_date'] : date('Y-m-d\TH:i'),
            'end_date' => isset($payload['end_date']) && is_string($payload['end_date']) ? $payload['end_date'] : date('Y-m-d\TH:i'),
            'description' => isset($payload['description']) && is_string($payload['description']) ? $payload['description'] : null,
            'published' => isset($payload['published']) ? (bool) $payload['published'] : false,
            'namespace' => $ns,
        ];

        $existing = $events->getAll($ns);
        $existing[] = $newEvent;
        $events->saveAll($existing, $ns);

        return $this->json($response, [
            'status' => 'created',
            'namespace' => $ns,
            'uid' => $uid,
        ], 201);
    }

    /**
     * PATCH /api/v1/namespaces/{ns}/events/{uid}
     */
    public function updateEvent(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $events = new EventService($pdo);
        $all = $events->getAll($ns);

        foreach ($all as &$e) {
            if ($e['uid'] !== $uid) {
                continue;
            }
            if (isset($payload['name']) && is_string($payload['name'])) {
                $e['name'] = trim($payload['name']);
            }
            if (isset($payload['slug']) && is_string($payload['slug'])) {
                $e['slug'] = trim($payload['slug']);
            }
            if (array_key_exists('start_date', $payload)) {
                $e['start_date'] = is_string($payload['start_date']) ? $payload['start_date'] : null;
            }
            if (array_key_exists('end_date', $payload)) {
                $e['end_date'] = is_string($payload['end_date']) ? $payload['end_date'] : null;
            }
            if (array_key_exists('description', $payload)) {
                $e['description'] = is_string($payload['description']) ? $payload['description'] : null;
            }
            if (array_key_exists('published', $payload)) {
                $e['published'] = (bool) $payload['published'];
            }
            break;
        }
        unset($e);

        $events->saveAll($all, $ns);

        return $this->json($response, ['status' => 'ok', 'namespace' => $ns, 'uid' => $uid]);
    }

    // ── Catalogs ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/namespaces/{ns}/events/{uid}/catalogs
     */
    public function listCatalogs(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $catalogs = $this->makeCatalogService($pdo, $uid);
        $raw = $catalogs->read('catalogs.json');
        $items = $raw !== null ? (json_decode($raw, true) ?? []) : [];

        return $this->json($response, ['namespace' => $ns, 'event_uid' => $uid, 'catalogs' => $items]);
    }

    /**
     * GET /api/v1/namespaces/{ns}/events/{uid}/catalogs/{slug}
     */
    public function getCatalog(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');
        $slug = (string) ($args['slug'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $catalogs = $this->makeCatalogService($pdo, $uid);
        $raw = $catalogs->read($slug . '.json');
        if ($raw === null) {
            return $this->json($response, ['error' => 'catalog_not_found'], 404);
        }

        $questions = json_decode($raw, true) ?? [];

        return $this->json($response, [
            'namespace' => $ns,
            'event_uid' => $uid,
            'slug' => $slug,
            'questions' => $questions,
        ]);
    }

    /**
     * PUT /api/v1/namespaces/{ns}/events/{uid}/catalogs/{slug}
     */
    public function upsertCatalog(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');
        $slug = (string) ($args['slug'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $questions = $payload['questions'] ?? null;
        if (!is_array($questions)) {
            return $this->json($response, ['error' => 'questions_required'], 422);
        }

        $catalogs = $this->makeCatalogService($pdo, $uid);

        // Ensure catalog entry exists
        $catalogs->createCatalog($slug . '.json');

        // Write questions
        $catalogs->write($slug . '.json', $questions);

        // Update catalog metadata if provided
        if (isset($payload['name']) || isset($payload['description']) || isset($payload['raetsel_buchstabe'])) {
            $raw = $catalogs->read('catalogs.json');
            $list = $raw !== null ? (json_decode($raw, true) ?? []) : [];
            foreach ($list as &$cat) {
                if (($cat['slug'] ?? '') === $slug) {
                    if (isset($payload['name']) && is_string($payload['name'])) {
                        $cat['name'] = trim($payload['name']);
                    }
                    if (isset($payload['description']) && is_string($payload['description'])) {
                        $cat['description'] = trim($payload['description']);
                    }
                    if (isset($payload['raetsel_buchstabe']) && is_string($payload['raetsel_buchstabe'])) {
                        $cat['raetsel_buchstabe'] = trim($payload['raetsel_buchstabe']);
                    }
                    break;
                }
            }
            unset($cat);
            $catalogs->write('catalogs.json', $list);
        }

        return $this->json($response, [
            'status' => 'ok',
            'namespace' => $ns,
            'event_uid' => $uid,
            'slug' => $slug,
        ]);
    }

    // ── Results ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/namespaces/{ns}/events/{uid}/results
     */
    public function listResults(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $results = new ResultService($pdo);

        return $this->json($response, [
            'namespace' => $ns,
            'event_uid' => $uid,
            'results' => $results->getAll($uid),
        ]);
    }

    /**
     * POST /api/v1/namespaces/{ns}/events/{uid}/results
     */
    public function submitResult(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $name = isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : '';
        $catalog = isset($payload['catalog']) && is_string($payload['catalog']) ? trim($payload['catalog']) : '';
        if ($name === '' || $catalog === '') {
            return $this->json($response, ['error' => 'name_and_catalog_required'], 422);
        }

        $results = new ResultService($pdo);
        $entry = $results->add($payload, $uid);

        return $this->json($response, [
            'status' => 'ok',
            'namespace' => $ns,
            'event_uid' => $uid,
            'result' => $entry,
        ], 201);
    }

    /**
     * DELETE /api/v1/namespaces/{ns}/events/{uid}/results
     */
    public function clearResults(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $results = new ResultService($pdo);
        $results->clear($uid);

        return $this->json($response, ['status' => 'ok', 'namespace' => $ns, 'event_uid' => $uid]);
    }

    // ── Teams ────────────────────────────────────────────────────────

    /**
     * GET /api/v1/namespaces/{ns}/events/{uid}/teams
     */
    public function listTeams(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $teams = $this->makeTeamService($pdo, $uid);

        return $this->json($response, [
            'namespace' => $ns,
            'event_uid' => $uid,
            'teams' => $teams->getAllForEvent($uid),
        ]);
    }

    /**
     * PUT /api/v1/namespaces/{ns}/events/{uid}/teams
     */
    public function replaceTeams(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $uid = (string) ($args['uid'] ?? '');

        $event = $this->resolveEventInNamespace($pdo, $uid, $ns);
        if ($event === null) {
            return $this->json($response, ['error' => 'event_not_found'], 404);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $teamNames = $payload['teams'] ?? null;
        if (!is_array($teamNames)) {
            return $this->json($response, ['error' => 'teams_array_required'], 422);
        }

        $filtered = [];
        foreach ($teamNames as $name) {
            if (is_string($name) && trim($name) !== '') {
                $filtered[] = trim($name);
            }
        }

        $teams = $this->makeTeamService($pdo, $uid);
        $teams->saveAll($filtered);

        return $this->json($response, [
            'status' => 'ok',
            'namespace' => $ns,
            'event_uid' => $uid,
            'count' => count($filtered),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function requireNamespaceMatch(Request $request, array $args): ?string
    {
        $ns = isset($args['ns']) ? (string) $args['ns'] : '';
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        if ($tokenNs === '' || $ns === '' || $ns !== $tokenNs) {
            return null;
        }
        return $ns;
    }

    private function resolvePdo(Request $request): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        return RequestDatabase::resolve($request);
    }

    /**
     * @return array{uid:string,slug:string,name:string,start_date:?string,end_date:?string,description:?string,published:bool,namespace:string}|null
     */
    private function resolveEventInNamespace(PDO $pdo, string $uid, string $ns): ?array
    {
        if ($uid === '') {
            return null;
        }
        $events = new EventService($pdo);
        return $events->getByUidInNamespace($uid, $ns);
    }

    private function makeCatalogService(PDO $pdo, string $eventUid): CatalogService
    {
        $config = new ConfigService($pdo);
        return new CatalogService($pdo, $config, null, '', $eventUid);
    }

    private function makeTeamService(PDO $pdo, string $eventUid): TeamService
    {
        $config = new ConfigService($pdo);
        $config->setActiveEventUid($eventUid);
        return new TeamService($pdo, $config);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
