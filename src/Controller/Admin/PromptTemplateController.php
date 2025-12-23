<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\PromptTemplateService;
use App\Service\TranslationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

use function array_filter;
use function array_key_exists;
use function array_map;
use function bin2hex;
use function is_array;
use function json_decode;
use function json_encode;
use function random_bytes;
use function trim;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class PromptTemplateController
{
    private PromptTemplateService $service;

    private ?TranslationService $translator;

    public function __construct(PromptTemplateService $service, ?TranslationService $translator = null)
    {
        $this->service = $service;
        $this->translator = $translator;
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        return $view->render($response, 'admin/prompt_templates.twig', [
            'csrfToken' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
        ]);
    }

    public function list(Request $request, Response $response): Response
    {
        $templates = $this->service->getAll();

        return $this->json($response, [
            'status' => 'ok',
            'templates' => $templates,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $payload = $this->parsePayload($request);
        $name = trim((string) ($payload['name'] ?? ''));
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        $id = (int) ($args['id'] ?? 0);

        if ($id <= 0 || $name === '' || $prompt === '') {
            return $this->jsonError($response, $this->translate('error_prompt_template_invalid'), 422);
        }

        try {
            $template = $this->service->update($id, $name, $prompt);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'not_found') {
                return $this->jsonError($response, $this->translate('error_prompt_template_missing'), 404);
            }

            if ($exception->getMessage() === 'invalid_payload') {
                return $this->jsonError($response, $this->translate('error_prompt_template_invalid'), 422);
            }

            return $this->jsonError($response, $this->translate('error_prompt_template_save'), 500);
        }

        return $this->json($response, [
            'status' => 'ok',
            'message' => $this->translate('notify_prompt_template_saved'),
            'template' => $template,
        ]);
    }

    private function parsePayload(Request $request): array
    {
        $data = $request->getParsedBody();
        if (is_array($data) && $data !== []) {
            return $data;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function translate(string $key): string
    {
        return $this->translator?->translate($key) ?? $key;
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $repository = new NamespaceRepository($pdo);
        try {
            $availableNamespaces = $repository->list();
        } catch (RuntimeException) {
            $availableNamespaces = [];
        }

        if ($accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
            )) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $currentNamespaceExists = array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        );
        if (!$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        if ($allowedNamespaces !== []) {
            foreach ($allowedNamespaces as $allowedNamespace) {
                if (!array_filter(
                    $availableNamespaces,
                    static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                )) {
                    $availableNamespaces[] = [
                        'namespace' => $allowedNamespace,
                        'label' => 'nicht gespeichert',
                        'is_active' => false,
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }
            }
        }

        $availableNamespaces = $accessService->filterNamespaceEntries($availableNamespaces, $allowedNamespaces, $role);

        return [$availableNamespaces, $namespace];
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = '{"status":"error","message":"serialization_failed"}';
        }

        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        return $this->json($response, [
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
}
