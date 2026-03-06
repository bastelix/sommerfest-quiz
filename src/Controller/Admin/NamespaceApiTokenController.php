<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\NamespaceApiTokenRepository;
use App\Service\PageService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class NamespaceApiTokenController
{
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?NamespaceApiTokenRepository $repo = null
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = \App\Support\CsrfTokenHelper::ensure();
        $namespace = (string) ($request->getQueryParams()['namespace'] ?? '');
        if ($namespace === '') {
            $namespace = (string) ($request->getAttribute('pageNamespace') ?? PageService::DEFAULT_NAMESPACE);
        }

        return $view->render($response, 'admin/namespace_api_tokens.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'csrf_token' => $csrf,
            'basePath' => $request->getAttribute('basePath') ?? '',
        ]);
    }

    public function list(Request $request, Response $response): Response
    {
        $namespace = (string) ($request->getQueryParams()['namespace'] ?? '');
        if ($namespace === '') {
            $namespace = (string) ($request->getAttribute('pageNamespace') ?? PageService::DEFAULT_NAMESPACE);
        }

        $repo = $this->getRepo($request);
        $tokens = $repo->listForNamespace($namespace);

        return $this->json($response, ['tokens' => $tokens]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $namespace = isset($data['namespace']) ? trim((string) $data['namespace']) : '';
        if ($namespace === '') {
            $namespace = (string) ($request->getAttribute('pageNamespace') ?? PageService::DEFAULT_NAMESPACE);
        }

        $label = isset($data['label']) ? trim((string) $data['label']) : '';
        $scopesRaw = $data['scopes'] ?? [
            'cms:write',
        ];

        $scopes = [];
        if (is_string($scopesRaw)) {
            $scopesRaw = preg_split('/\s*,\s*/', $scopesRaw) ?: [];
        }
        if (is_array($scopesRaw)) {
            foreach ($scopesRaw as $s) {
                if (is_string($s)) {
                    $s = trim($s);
                    if ($s !== '') {
                        $scopes[] = $s;
                    }
                }
            }
        }
        if ($scopes === []) {
            $scopes = ['cms:write'];
        }

        $repo = $this->getRepo($request);
        $created = $repo->create($namespace, $label, $scopes);

        return $this->json($response, [
            'status' => 'created',
            'id' => $created['id'],
            'token' => $created['token'],
            'namespace' => $namespace,
            'scopes' => $scopes,
        ], 201);
    }

    public function revoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $repo = $this->getRepo($request);
        $repo->revoke($id);

        return $this->json($response, ['status' => 'revoked']);
    }

    private function getRepo(Request $request): NamespaceApiTokenRepository
    {
        $pdo = $this->pdo;
        if (!$pdo instanceof PDO) {
            $pdo = RequestDatabase::resolve($request);
        }

        return $this->repo ?? new NamespaceApiTokenRepository($pdo);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
