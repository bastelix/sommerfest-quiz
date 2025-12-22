<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Exception\DuplicateNamespaceException;
use App\Exception\NamespaceInUseException;
use App\Exception\NamespaceNotFoundException;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Repository\UserNamespaceRepository;
use App\Service\NamespaceService;
use App\Service\NamespaceValidator;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\TranslationService;
use InvalidArgumentException;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

/**
 * Admin controller for namespace management.
 */
final class NamespaceController
{
    private NamespaceValidator $validator;

    public function __construct(private NamespaceService $service, ?NamespaceValidator $validator = null)
    {
        $this->validator = $validator ?? new NamespaceValidator();
    }

    /**
     * Render the namespace management page.
     */
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        return $view->render($response, 'admin/namespace_management.twig', [
            'csrfToken' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
            'defaultNamespace' => PageService::DEFAULT_NAMESPACE,
            'namespacePattern' => $this->validator->getPattern(),
            'namespaceMaxLength' => $this->validator->getMaxLength(),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
        ]);
    }

    /**
     * Provide namespace data as JSON.
     */
    public function list(Request $request, Response $response): Response
    {
        try {
            $entries = $this->service->all();
        } catch (RuntimeException) {
            return $this->jsonError(
                $response,
                $this->translate(
                    $request,
                    'error_namespace_table_missing',
                    'The namespaces table is missing. Please run the migrations.'
                ),
                500
            );
        }

        $namespaces = array_map(
            function (array $entry): array {
                $namespace = $entry['namespace'];
                return [
                    'namespace' => $namespace,
                    'label' => $entry['label'] ?? null,
                    'created_at' => $entry['created_at'] ?? null,
                    'is_active' => $entry['is_active'],
                    'is_default' => $namespace === PageService::DEFAULT_NAMESPACE,
                ];
            },
            $entries
        );

        return $this->json($response, [
            'namespaces' => $namespaces,
        ]);
    }

    /**
     * Create a new namespace record.
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $this->parsePayload($request);
        $namespace = is_array($data) ? (string) ($data['namespace'] ?? '') : '';
        $labelPayload = $this->parseLabelPayload($data);
        $normalized = $this->validator->normalize($namespace);

        $validationError = $this->validateNamespace($request, $response, $normalized);
        if ($validationError instanceof Response) {
            return $validationError;
        }

        try {
            $entry = $this->service->create($normalized, $labelPayload['label']);
            $this->grantUserNamespaceAccess($request, $normalized);
        } catch (DuplicateNamespaceException) {
            return $this->jsonError($response, $this->translate($request, 'error_namespace_duplicate', 'Namespace exists.'), 409);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $this->resolveNamespaceError($request, $exception), 422);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        return $this->json($response, [
            'status' => 'created',
            'namespace' => $entry,
        ], 201);
    }

    /**
     * Rename an existing namespace record.
     *
     * @param array{namespace:string} $args
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $source = (string) $args['namespace'];
        $data = $this->parsePayload($request);
        $target = is_array($data) ? (string) ($data['namespace'] ?? '') : '';
        $labelPayload = $this->parseLabelPayload($data);
        $normalized = $this->validator->normalize($target);

        $validationError = $this->validateNamespace($request, $response, $normalized);
        if ($validationError instanceof Response) {
            return $validationError;
        }

        try {
            $entry = $this->service->rename($source, $normalized, $labelPayload['label'], $labelPayload['provided']);
        } catch (NamespaceNotFoundException) {
            return $this->jsonError($response, $this->translate($request, 'error_namespace_not_found', 'Namespace not found.'), 404);
        } catch (DuplicateNamespaceException) {
            return $this->jsonError($response, $this->translate($request, 'error_namespace_duplicate', 'Namespace exists.'), 409);
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'default-namespace') {
                return $this->jsonError(
                    $response,
                    $this->translate($request, 'error_namespace_default_locked', 'Default namespace cannot be changed.'),
                    422
                );
            }

            return $this->jsonError($response, $this->resolveNamespaceError($request, $exception), 422);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        return $this->json($response, [
            'status' => 'updated',
            'namespace' => $entry,
        ]);
    }

    /**
     * Delete an existing namespace record.
     *
     * @param array{namespace:string} $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $namespace = (string) $args['namespace'];

        try {
            $this->service->delete($namespace);
        } catch (NamespaceInUseException $exception) {
            $message = $this->translate(
                $request,
                'error_namespace_in_use',
                'Namespace is still in use.'
            );
            $sources = $exception->getSources();
            if ($sources !== []) {
                $message .= ' ' . implode(', ', $sources);
            }

            return $this->jsonError($response, $message, 409);
        } catch (NamespaceNotFoundException) {
            return $this->jsonError($response, $this->translate($request, 'error_namespace_not_found', 'Namespace not found.'), 404);
        } catch (InvalidArgumentException) {
            return $this->jsonError($response, $this->translate($request, 'error_namespace_default_locked', 'Default namespace cannot be changed.'), 422);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        return $this->json($response, ['status' => 'deleted']);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parsePayload(Request $request): ?array
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return array{label:?string,provided:bool}
     */
    private function parseLabelPayload(?array $data): array
    {
        if ($data === null || !array_key_exists('label', $data)) {
            return ['label' => null, 'provided' => false];
        }

        $label = $data['label'];
        if ($label === null) {
            return ['label' => null, 'provided' => true];
        }

        return ['label' => is_scalar($label) ? (string) $label : null, 'provided' => true];
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        return $this->json($response, ['error' => $message], $status);
    }

    private function validateNamespace(Request $request, Response $response, string $namespace): ?Response
    {
        try {
            $this->validator->assertValid($namespace);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $this->resolveNamespaceError($request, $exception), 422);
        }

        return null;
    }

    private function resolveNamespaceError(Request $request, InvalidArgumentException $exception): string
    {
        return match ($exception->getMessage()) {
            'namespace-empty' => $this->translate(
                $request,
                'error_namespace_empty',
                'Please enter a namespace.'
            ),
            'namespace-length' => $this->translate(
                $request,
                'error_namespace_invalid_length',
                'Namespace is too long.'
            ),
            'namespace-format' => $this->translate(
                $request,
                'error_namespace_invalid_format',
                'Namespace format is invalid.'
            ),
            default => $this->translate($request, 'error_namespace_invalid', 'Invalid namespace.'),
        };
    }

    private function translate(Request $request, string $key, string $fallback): string
    {
        $translator = $request->getAttribute('translator');
        if ($translator instanceof TranslationService) {
            return $translator->translate($key);
        }

        return $fallback;
    }

    private function grantUserNamespaceAccess(Request $request, string $namespace): void
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }

        $repository = new UserNamespaceRepository($pdo);
        $repository->addNamespaceForUser($userId, $namespace);

        $this->updateSessionNamespaces($namespace);
    }

    private function updateSessionNamespaces(string $namespace): void
    {
        if (!isset($_SESSION['user']['namespaces']) || !is_array($_SESSION['user']['namespaces'])) {
            return;
        }

        $normalized = strtolower(trim($namespace));
        if ($normalized === '') {
            return;
        }

        foreach ($_SESSION['user']['namespaces'] as $entry) {
            $current = strtolower(trim((string) ($entry['namespace'] ?? '')));
            if ($current !== '' && $current === $normalized) {
                return;
            }
        }

        $_SESSION['user']['namespaces'][] = [
            'namespace' => $normalized,
            'is_default' => false,
        ];
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $repository = new NamespaceRepository($pdo);
        try {
            $availableNamespaces = $repository->list();
        } catch (RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if (!array_filter(
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
        if (!$currentNamespaceExists) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [$availableNamespaces, $namespace];
    }
}
