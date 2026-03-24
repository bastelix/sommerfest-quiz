<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\LandingNewsService;
use App\Support\RequestDatabase;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NamespaceNewsController
{
    public const SCOPE_NEWS_READ = 'news:read';
    public const SCOPE_NEWS_WRITE = 'news:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?LandingNewsService $news = null,
    ) {
    }

    /**
     * GET /api/v1/namespaces/{ns}/news
     */
    public function list(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->news ?? new LandingNewsService($pdo);

        $items = [];
        foreach ($svc->getAllForNamespace($ns) as $news) {
            $items[] = $news->jsonSerialize();
        }

        return $this->json($response, ['namespace' => $ns, 'news' => $items]);
    }

    /**
     * POST /api/v1/namespaces/{ns}/news
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $pageId = isset($payload['pageId']) && is_numeric($payload['pageId']) ? (int) $payload['pageId'] : 0;
        $slug = isset($payload['slug']) && is_string($payload['slug']) ? trim($payload['slug']) : '';
        $title = isset($payload['title']) && is_string($payload['title']) ? trim($payload['title']) : '';
        $content = isset($payload['content']) && is_string($payload['content']) ? $payload['content'] : '';

        if ($pageId <= 0 || $slug === '' || $title === '' || trim($content) === '') {
            return $this->json($response, ['error' => 'missing_required_fields'], 422);
        }

        $excerpt = isset($payload['excerpt']) && is_string($payload['excerpt']) ? $payload['excerpt'] : null;
        $imageUrl = isset($payload['imageUrl']) && is_string($payload['imageUrl']) ? $payload['imageUrl'] : null;
        $isPublished = isset($payload['isPublished']) ? (bool) $payload['isPublished'] : false;

        $publishedAt = null;
        if (isset($payload['publishedAt']) && is_string($payload['publishedAt'])) {
            try {
                $publishedAt = new DateTimeImmutable($payload['publishedAt'], new DateTimeZone('UTC'));
            } catch (\Throwable $e) {
                return $this->json($response, ['error' => 'invalid_published_at'], 422);
            }
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->news ?? new LandingNewsService($pdo);

        try {
            $news = $svc->create(
                $pageId,
                $slug,
                $title,
                $excerpt,
                $content,
                $publishedAt,
                $isPublished,
                $imageUrl
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        } catch (\LogicException $e) {
            return $this->json($response, ['error' => 'conflict', 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'create_failed', 'message' => $e->getMessage()], 500);
        }

        return $this->json($response, [
            'status' => 'created',
            'news' => $news->jsonSerialize(),
        ], 201);
    }

    /**
     * GET /api/v1/namespaces/{ns}/news/{id}
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->news ?? new LandingNewsService($pdo);

        $news = $svc->find($id);
        if ($news === null) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        return $this->json($response, ['namespace' => $ns, 'news' => $news->jsonSerialize()]);
    }

    /**
     * PATCH /api/v1/namespaces/{ns}/news/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->news ?? new LandingNewsService($pdo);

        $existing = $svc->find($id);
        if ($existing === null) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $pageId = isset($payload['pageId']) && is_numeric($payload['pageId'])
            ? (int) $payload['pageId'] : $existing->getPageId();
        $slug = isset($payload['slug']) && is_string($payload['slug'])
            ? trim($payload['slug']) : $existing->getSlug();
        $title = isset($payload['title']) && is_string($payload['title'])
            ? trim($payload['title']) : $existing->getTitle();
        $content = isset($payload['content']) && is_string($payload['content'])
            ? $payload['content'] : $existing->getContent();

        if (trim($content) === '') {
            return $this->json($response, ['error' => 'content_cannot_be_empty'], 422);
        }

        $excerpt = array_key_exists('excerpt', $payload) && is_string($payload['excerpt'])
            ? $payload['excerpt'] : $existing->getExcerpt();
        $imageUrl = array_key_exists('imageUrl', $payload) && is_string($payload['imageUrl'])
            ? $payload['imageUrl'] : $existing->getImageUrl();
        $isPublished = isset($payload['isPublished']) ? (bool) $payload['isPublished'] : $existing->isPublished();

        $publishedAt = $existing->getPublishedAt();
        if (array_key_exists('publishedAt', $payload)) {
            if ($payload['publishedAt'] === null) {
                $publishedAt = null;
            } elseif (is_string($payload['publishedAt'])) {
                try {
                    $publishedAt = new DateTimeImmutable($payload['publishedAt'], new DateTimeZone('UTC'));
                } catch (\Throwable $e) {
                    return $this->json($response, ['error' => 'invalid_published_at'], 422);
                }
            }
        }

        try {
            $news = $svc->update(
                $id,
                $pageId,
                $slug,
                $title,
                $excerpt,
                $content,
                $publishedAt,
                $isPublished,
                $imageUrl
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        } catch (\LogicException $e) {
            return $this->json($response, ['error' => 'conflict', 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'update_failed', 'message' => $e->getMessage()], 500);
        }

        return $this->json($response, [
            'status' => 'updated',
            'news' => $news->jsonSerialize(),
        ]);
    }

    /**
     * DELETE /api/v1/namespaces/{ns}/news/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->news ?? new LandingNewsService($pdo);

        $existing = $svc->find($id);
        if ($existing === null) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $svc->delete($id);

        return $this->json($response, ['status' => 'deleted']);
    }

    private function resolvePdo(Request $request): PDO
    {
        $pdo = $this->pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        return RequestDatabase::resolve($request);
    }

    private function requireNamespaceMatch(Request $request, array $args): ?string
    {
        $ns = isset($args['ns']) ? (string) $args['ns'] : '';
        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        if ($ns === '' || $tokenNs === '' || $ns !== $tokenNs) {
            return null;
        }
        return $ns;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
