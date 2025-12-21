<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\MailProviderRepository;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\DomainStartPageService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\SettingsService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;
use Throwable;

class MailProviderController
{
    private ?MailProviderRepository $repository;

    private SettingsService $settings;

    private MailProviderManager $manager;

    private ?DomainStartPageService $domainStartPages;

    /**
     * @var array<string,array<string,string>>
     */
    private array $providerMeta = [
        'brevo' => [
            'label' => 'Brevo',
            'description' => 'Brevo (Sendinblue)',
        ],
        'mailchimp' => [
            'label' => 'Mailchimp',
            'description' => 'Mailchimp (Mandrill)',
        ],
        'sendgrid' => [
            'label' => 'SendGrid',
            'description' => 'SendGrid',
        ],
    ];

    public function __construct(
        ?MailProviderRepository $repository,
        SettingsService $settings,
        MailProviderManager $manager,
        ?DomainStartPageService $domainStartPages = null
    ) {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->manager = $manager;
        $this->domainStartPages = $domainStartPages;
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $role = (string) ($_SESSION['user']['role'] ?? '');

        $providers = [];
        $active = null;
        $error = null;

        if ($this->repository === null) {
            $error = $error;
        } else {
            try {
                $stored = $this->repository->all();
                $activeRow = $this->repository->findActive();
                $active = $activeRow['provider_name'] ?? (string) $this->settings->get('mail_provider', 'brevo');
                $providers = $this->mergeWithDefaults($stored);
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        return $view->render($response, 'admin/mail_providers.twig', [
            'providers' => $providers,
            'activeProvider' => $active,
            'providerMeta' => $this->providerMeta,
            'csrfToken' => $csrf,
            'role' => $role,
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'errorMessage' => $error,
            'domainOverrides' => $this->collectDomainOverrides(),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        if ($this->repository === null) {
            return $this->jsonError($response, 'Mail provider repository is not available.', 500);
        }

        $data = $this->parseJsonBody($request);
        if (!is_array($data)) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $provider = strtolower(trim((string) ($data['provider'] ?? '')));
        if ($provider === '') {
            return $this->jsonError($response, 'Missing provider.', 422);
        }

        $settings = [
            'from_email' => trim((string) ($data['from_email'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
            'mailer_dsn' => trim((string) ($data['mailer_dsn'] ?? '')),
        ];

        $payload = [
            'api_key' => $data['api_key'] ?? null,
            'list_id' => $data['list_id'] ?? null,
            'smtp_host' => $data['smtp_host'] ?? null,
            'smtp_user' => $data['smtp_user'] ?? null,
            'smtp_pass' => $data['smtp_pass'] ?? null,
            'smtp_port' => $data['smtp_port'] ?? null,
            'smtp_encryption' => $data['smtp_encryption'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
            'settings' => $settings,
        ];

        if ($payload['active']) {
            $required = ['smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port'];
            foreach ($required as $field) {
                if (trim((string) ($payload[$field] ?? '')) === ''
                    && $settings['mailer_dsn'] === '') {
                    return $this->jsonError($response, sprintf('Field %s is required.', $field), 422);
                }
            }
            if ($settings['from_email'] === '' && $settings['mailer_dsn'] === '') {
                return $this->jsonError($response, 'Sender email is required.', 422);
            }
        }

        try {
            $this->repository->save($provider, $payload);
            if ($payload['active']) {
                $this->repository->activate($provider);
                $this->settings->save(['mail_provider' => $provider]);
            } else {
                $this->repository->deactivate($provider);
            }
            $this->manager->refresh();
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        return $this->jsonSuccess($response, [
            'status' => 'saved',
            'provider' => $provider,
        ]);
    }

    public function testConnection(Request $request, Response $response): Response
    {
        if ($this->repository === null) {
            return $this->jsonError($response, 'Mail provider repository is not available.', 500);
        }

        $data = $this->parseJsonBody($request);
        if (!is_array($data)) {
            return $this->jsonError($response, 'Invalid payload.', 400);
        }

        $provider = strtolower(trim((string) ($data['provider'] ?? '')));
        $config = null;

        try {
            if ($provider !== '') {
                $config = $this->repository->find($provider);
            }
            if ($config === null) {
                $config = $this->repository->findActive();
            }
        } catch (RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 500);
        }

        if ($config === null) {
            return $this->jsonError($response, 'No provider configured.', 404);
        }

        $name = (string) ($config['provider_name'] ?? $provider);
        try {
            $providerInstance = $this->manager->createProvider($name, $config);
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
            'provider' => $name,
            'details' => $status,
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $stored
     * @return array<int,array<string,mixed>>
     */
    private function mergeWithDefaults(array $stored): array
    {
        $byName = [];
        foreach ($stored as $row) {
            $name = strtolower((string) ($row['provider_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $byName[$name] = $row;
        }

        $result = [];
        foreach (array_keys($this->providerMeta) as $name) {
            $entry = $byName[$name] ?? [
                'provider_name' => $name,
                'api_key' => null,
                'list_id' => null,
                'smtp_host' => null,
                'smtp_user' => null,
                'smtp_pass' => null,
                'smtp_port' => null,
                'smtp_encryption' => null,
                'active' => false,
                'settings' => [
                    'from_email' => '',
                    'from_name' => '',
                    'mailer_dsn' => '',
                ],
            ];

            $settings = $entry['settings'] ?? [];
            if (!is_array($settings)) {
                $settings = [];
            }
            $entry['settings'] = array_merge([
                'from_email' => '',
                'from_name' => '',
                'mailer_dsn' => '',
            ], $settings);

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return array<int,array<string,bool|string>>
     */
    private function collectDomainOverrides(): array
    {
        if (!$this->domainStartPages instanceof DomainStartPageService) {
            return [];
        }

        try {
            $mappings = $this->domainStartPages->getAllMappings();
        } catch (Throwable $exception) {
            error_log('Failed to load domain SMTP overrides: ' . $exception->getMessage());

            return [];
        }

        $overrides = [];
        foreach ($mappings as $domain => $config) {
            $dsn = $this->normalizeString($config['smtp_dsn'] ?? null);
            $host = $this->normalizeString($config['smtp_host'] ?? null);
            $user = $this->normalizeString($config['smtp_user'] ?? null);
            $encryption = $this->normalizeString($config['smtp_encryption'] ?? null);
            $hasPassword = (bool) $config['has_smtp_pass'];
            $port = $config['smtp_port'] ?? null;
            $hasPort = is_int($port) && $port > 0;

            $hasDsn = $dsn !== null;
            $hasCredentials = $host !== null || $user !== null || $hasPassword || $hasPort || $encryption !== null;

            if (!$hasDsn && !$hasCredentials) {
                continue;
            }

            $overrides[] = [
                'domain' => (string) $domain,
                'has_dsn' => $hasDsn,
                'has_credentials' => $hasCredentials,
            ];
        }

        usort(
            $overrides,
            static fn (array $left, array $right): int => strcmp((string) $left['domain'], (string) $right['domain'])
        );

        return $overrides;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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
            static fn (array $entry): bool => ($entry['namespace'] ?? '') === PageService::DEFAULT_NAMESPACE
        )) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        if (!array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => ($entry['namespace'] ?? '') === $namespace
        )) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [$availableNamespaces, $namespace];
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
