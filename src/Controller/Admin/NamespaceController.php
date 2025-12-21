<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Exception\DuplicateNamespaceException;
use App\Exception\NamespaceInUseException;
use App\Exception\NamespaceNotFoundException;
use App\Service\NamespaceService;
use App\Service\NamespaceValidator;
use App\Service\PageService;
use App\Service\TranslationService;
use InvalidArgumentException;
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

        return $view->render($response, 'admin/namespace_management.twig', [
            'csrfToken' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
            'defaultNamespace' => PageService::DEFAULT_NAMESPACE,
            'namespacePattern' => $this->validator->getPattern(),
            'namespaceMaxLength' => $this->validator->getMaxLength(),
        ]);
    }

    /**
     * Provide namespace data as JSON.
     */
    public function list(Request $request, Response $response): Response
    {
        $namespaces = array_map(
            function (array $entry): array {
                $namespace = $entry['namespace'];
                return [
                    'namespace' => $namespace,
                    'created_at' => $entry['created_at'] ?? null,
                    'is_active' => $entry['is_active'] ?? true,
                    'is_default' => $namespace === PageService::DEFAULT_NAMESPACE,
                ];
            },
            $this->service->all()
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
        $normalized = $this->validator->normalize($namespace);

        $validationError = $this->validateNamespace($request, $response, $normalized);
        if ($validationError instanceof Response) {
            return $validationError;
        }

        try {
            $entry = $this->service->create($normalized);
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
        $source = isset($args['namespace']) ? (string) $args['namespace'] : '';
        $data = $this->parsePayload($request);
        $target = is_array($data) ? (string) ($data['namespace'] ?? '') : '';
        $normalized = $this->validator->normalize($target);

        $validationError = $this->validateNamespace($request, $response, $normalized);
        if ($validationError instanceof Response) {
            return $validationError;
        }

        try {
            $entry = $this->service->rename($source, $normalized);
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
        $namespace = isset($args['namespace']) ? (string) $args['namespace'] : '';

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
}
