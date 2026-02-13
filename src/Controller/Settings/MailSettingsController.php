<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Infrastructure\Database;
use App\Infrastructure\MailProviderRepository;
use App\Repository\NamespaceRepository;
use App\Service\MailProvider\MailProviderManager;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\SettingsService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

class MailSettingsController
{
    private ?MailProviderRepository $repository;

    private SettingsService $settings;

    public function __construct(
        ?MailProviderRepository $repository,
        SettingsService $settings
    ) {
        $this->repository = $repository;
        $this->settings = $settings;
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $role = (string) ($_SESSION['user']['role'] ?? '');
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        $config = null;
        $hasCustomSmtp = false;
        $adminConfigured = false;
        $error = null;

        if ($this->repository === null) {
            $error = 'Mail provider repository is not available.';
        } else {
            try {
                $config = $this->repository->find('brevo', $namespace);
                $activeRow = $this->repository->findActive($namespace);
                if (
                    $activeRow !== null
                    && ($activeRow['provider_name'] ?? '') !== 'brevo'
                ) {
                    $adminConfigured = true;
                }
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        if ($config !== null) {
            $settings = $config['settings'] ?? [];
            if (!is_array($settings)) {
                $settings = [];
            }
            $config['settings'] = array_merge([
                'from_email' => '',
                'from_name' => '',
                'mailer_dsn' => '',
            ], $settings);

            $hasCustomSmtp = trim((string) ($config['smtp_host'] ?? '')) !== '';
        } else {
            $config = [
                'smtp_host' => null,
                'smtp_user' => null,
                'smtp_pass' => null,
                'smtp_port' => null,
                'smtp_encryption' => null,
                'settings' => [
                    'from_email' => '',
                    'from_name' => '',
                    'mailer_dsn' => '',
                ],
            ];
        }

        return $view->render($response, 'settings/mail.twig', [
            'config' => $config,
            'hasCustomSmtp' => $hasCustomSmtp,
            'adminConfigured' => $adminConfigured,
            'csrfToken' => $csrf,
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'errorMessage' => $error,
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        if ($this->repository === null) {
            return $this->jsonError($response, 'Mail provider repository is not available.', 500);
        }

        $namespace = $this->resolveNamespace($request);

        $data = $this->parseJsonBody($request);
        if (!is_array($data)) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $fromEmail = trim((string) ($data['from_email'] ?? ''));
        $fromName = trim((string) ($data['from_name'] ?? ''));
        $useCustomSmtp = (bool) ($data['use_custom_smtp'] ?? false);

        if ($fromEmail === '') {
            return $this->jsonError($response, 'Sender email is required.', 422);
        }

        if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            return $this->jsonError($response, 'Invalid email address.', 422);
        }

        $smtpHost = null;
        $smtpUser = null;
        $smtpPass = null;
        $smtpPort = null;
        $smtpEncryption = null;

        if ($useCustomSmtp) {
            $smtpHost = trim((string) ($data['smtp_host'] ?? ''));
            $smtpUser = trim((string) ($data['smtp_user'] ?? ''));
            $smtpPass = (string) ($data['smtp_pass'] ?? '');
            $smtpPort = trim((string) ($data['smtp_port'] ?? ''));
            $smtpEncryption = trim((string) ($data['smtp_encryption'] ?? 'tls'));

            $required = ['smtp_host' => $smtpHost, 'smtp_user' => $smtpUser, 'smtp_pass' => $smtpPass, 'smtp_port' => $smtpPort];
            foreach ($required as $field => $value) {
                if ($value === '') {
                    return $this->jsonError($response, sprintf('Field %s is required when using custom SMTP.', $field), 422);
                }
            }
        }

        $payload = [
            'api_key' => null,
            'list_id' => null,
            'smtp_host' => $smtpHost,
            'smtp_user' => $smtpUser,
            'smtp_pass' => $smtpPass,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'active' => true,
            'settings' => [
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'mailer_dsn' => '',
            ],
        ];

        try {
            $this->repository->save('brevo', $payload, $namespace);
            $this->repository->activate('brevo', $namespace);
            $this->resolveManager($namespace)->refresh();
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        return $this->jsonSuccess($response, [
            'status' => 'saved',
        ]);
    }

    public function testConnection(Request $request, Response $response): Response
    {
        if ($this->repository === null) {
            return $this->jsonError($response, 'Mail provider repository is not available.', 500);
        }

        $namespace = $this->resolveNamespace($request);

        $config = null;
        try {
            $config = $this->repository->find('brevo', $namespace);
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        if ($config === null) {
            return $this->jsonError($response, 'No provider configured.', 404);
        }

        try {
            $providerInstance = $this->resolveManager($namespace)->createProvider('brevo', $config);
            $status = $providerInstance->getStatus();
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        if (!(bool) ($status['configured'] ?? false)) {
            $missingRaw = $status['missing'] ?? [];
            $missing = [];
            if (is_array($missingRaw)) {
                foreach ($missingRaw as $item) {
                    $label = trim((string) $item);
                    if ($label === '') {
                        continue;
                    }
                    $missing[] = $label;
                }
            }
            $message = 'Provider is not fully configured.';
            if ($missing !== []) {
                $message .= ' Missing: ' . implode(', ', $missing);
            }

            return $this->jsonError($response, $message, 422);
        }

        return $this->jsonSuccess($response, [
            'status' => 'ok',
            'details' => $status,
        ]);
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
        } catch (RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if (
            $accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
            )
        ) {
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
        if (
            !$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)
        ) {
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
                if (
                    !array_filter(
                        $availableNamespaces,
                        static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                    )
                ) {
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

    private function resolveNamespace(Request $request): string
    {
        return (new NamespaceResolver())->resolve($request)->getNamespace();
    }

    private function resolveManager(string $namespace): MailProviderManager
    {
        if ($this->repository instanceof MailProviderRepository) {
            return new MailProviderManager($this->settings, [], $this->repository, $namespace);
        }

        throw new RuntimeException('Mail provider repository is not available.');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parseJsonBody(Request $request): ?array
    {
        $data = $request->getParsedBody();
        if (is_array($data) && $data !== []) {
            return $data;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message,
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function jsonSuccess(Response $response, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
