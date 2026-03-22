<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\DesignTokenService;
use App\Service\PageService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NamespaceDesignController
{
    public const SCOPE_DESIGN_READ = 'design:read';
    public const SCOPE_DESIGN_WRITE = 'design:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
    ) {
    }

    /**
     * GET /api/v1/namespaces/{ns}/design
     *
     * Returns the complete design manifest including token hierarchy,
     * resolved values, block token options, and legacy aliases.
     */
    public function manifest(Request $request, Response $response, array $args): Response
    {
        $ns = $this->resolveNamespace($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $service = new DesignTokenService($pdo);

        try {
            $manifest = $service->getDesignManifest($ns);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }

        return $this->json($response, $manifest);
    }

    /**
     * GET /api/v1/namespaces/{ns}/design/tokens
     *
     * Returns the current design tokens for the namespace.
     */
    public function getTokens(Request $request, Response $response, array $args): Response
    {
        $ns = $this->resolveNamespace($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $service = new DesignTokenService($pdo);

        try {
            $tokens = $service->getTokensForNamespace($ns);
            $importMeta = $service->getImportMeta($ns);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }

        return $this->json($response, [
            'namespace' => $ns,
            'tokens' => $tokens,
            'importMeta' => $importMeta,
        ]);
    }

    /**
     * PATCH /api/v1/namespaces/{ns}/design/tokens
     *
     * Partially update design tokens. Only provided fields are changed.
     */
    public function updateTokens(Request $request, Response $response, array $args): Response
    {
        $ns = $this->resolveNamespace($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $body = $request->getParsedBody();
        $tokenUpdates = $body['tokens'] ?? null;
        if (!is_array($tokenUpdates)) {
            return $this->json($response, ['error' => 'tokens must be an object'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $service = new DesignTokenService($pdo);

        try {
            $current = $service->getTokensForNamespace($ns);
            foreach ($tokenUpdates as $group => $values) {
                if (!is_array($values) || !array_key_exists($group, $current)) {
                    continue;
                }
                foreach ($values as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $current[$group][$key] = $value;
                    }
                }
            }
            $persisted = $service->persistTokens($ns, $current);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }

        return $this->json($response, [
            'namespace' => $ns,
            'tokens' => $persisted,
        ]);
    }

    /**
     * POST /api/v1/namespaces/{ns}/design/validate/{slug}
     *
     * Validate the design of a specific page.
     */
    public function validate(Request $request, Response $response, array $args): Response
    {
        $ns = $this->resolveNamespace($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $slug = isset($args['slug']) ? (string) $args['slug'] : '';
        if ($slug === '') {
            return $this->json($response, ['error' => 'slug is required'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $pageService = new PageService($pdo);
        $content = $pageService->getByKey($ns, $slug);

        if ($content === null) {
            return $this->json($response, ['error' => "Page '{$slug}' not found"], 404);
        }

        $designService = new DesignTokenService($pdo);

        try {
            $result = $designService->validatePageDesign($ns, $content);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }

        return $this->json($response, $result);
    }

    private function resolveNamespace(Request $request, array $args): ?string
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
        return $this->pdo instanceof PDO ? $this->pdo : RequestDatabase::resolve($request);
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
