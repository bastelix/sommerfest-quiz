<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Ticket;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\TicketService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Throwable;

use function array_filter;
use function http_build_query;
use function in_array;
use function is_array;
use function is_string;
use function trim;

final class TicketController
{
    private TicketService $tickets;
    private NamespaceResolver $namespaceResolver;
    private ProjectSettingsService $projectSettings;

    private const ALLOWED_TRANSITIONS = [
        Ticket::STATUS_OPEN => [Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_CLOSED],
        Ticket::STATUS_IN_PROGRESS => [Ticket::STATUS_RESOLVED, Ticket::STATUS_OPEN],
        Ticket::STATUS_RESOLVED => [Ticket::STATUS_CLOSED, Ticket::STATUS_IN_PROGRESS],
        Ticket::STATUS_CLOSED => [Ticket::STATUS_OPEN],
    ];

    public function __construct(
        ?TicketService $tickets = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?ProjectSettingsService $projectSettings = null
    ) {
        $this->tickets = $tickets ?? new TicketService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService();
    }

    // ── List ─────────────────────────────────────────────────────────

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = isset($params['status']) ? (string) $params['status'] : '';
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        $filters = [];
        foreach (['filterStatus' => 'status', 'filterPriority' => 'priority', 'filterType' => 'type'] as $param => $key) {
            $value = isset($params[$param]) ? trim((string) $params[$param]) : '';
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        $entries = $this->tickets->listByNamespace($namespace, $filters);
        $ticketSettings = $this->projectSettings->getTicketSettings($namespace);

        return Twig::fromRequest($request)->render($response, 'admin/tickets/index.twig', [
            'entries' => $entries,
            'status' => $this->normalizeFlashStatus($status),
            'filters' => $filters,
            'ticketSettings' => $ticketSettings,
            'csrfToken' => $this->ensureCsrfToken(),
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'available_namespaces' => $availableNamespaces,
            'pageTab' => 'tickets',
        ]);
    }

    // ── Detail ───────────────────────────────────────────────────────

    public function show(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        $comments = $this->tickets->listComments($ticket->getId());
        $transitions = self::ALLOWED_TRANSITIONS[$ticket->getStatus()] ?? [];
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        return Twig::fromRequest($request)->render($response, 'admin/tickets/show.twig', [
            'ticket' => $ticket,
            'comments' => $comments,
            'transitions' => $transitions,
            'csrfToken' => $this->ensureCsrfToken(),
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'available_namespaces' => $availableNamespaces,
            'pageTab' => 'tickets',
        ]);
    }

    // ── Create ───────────────────────────────────────────────────────

    public function create(Request $request, Response $response): Response
    {
        return $this->renderForm($request, $response, null);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $this->extractFormData($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        try {
            $this->tickets->create(
                $namespace,
                $data['title'],
                $data['description'],
                $data['type'],
                $data['priority'],
                null,
                null,
                $data['assignee'] !== '' ? $data['assignee'] : null,
                $data['labels'],
                $data['due_date'] !== '' ? $data['due_date'] : null,
                $_SESSION['user']['username'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->renderForm($request, $response, null, $data, $e->getMessage());
        }

        return $this->redirectToList($request, $response, 'created');
    }

    // ── Edit ─────────────────────────────────────────────────────────

    public function edit(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        return $this->renderForm($request, $response, $ticket);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        $data = $this->extractFormData($request);

        try {
            $this->tickets->update($ticket->getId(), [
                'title' => $data['title'],
                'description' => $data['description'],
                'type' => $data['type'],
                'priority' => $data['priority'],
                'assignee' => $data['assignee'] !== '' ? $data['assignee'] : null,
                'labels' => $data['labels'],
                'dueDate' => $data['due_date'] !== '' ? $data['due_date'] : null,
            ]);
        } catch (Throwable $e) {
            return $this->renderForm($request, $response, $ticket, $data, $e->getMessage());
        }

        return $this->redirectToList($request, $response, 'updated');
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function delete(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket !== null) {
            $this->tickets->delete($ticket->getId());
        }

        return $this->redirectToList($request, $response, 'deleted');
    }

    // ── Status transition ────────────────────────────────────────────

    public function transition(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $newStatus = is_array($data) && isset($data['status']) ? trim((string) $data['status']) : '';

        try {
            $this->tickets->transition($ticket->getId(), $newStatus);
        } catch (Throwable $e) {
            // Redirect back to show with error visible via flash
        }

        return $this->redirectToShow($request, $response, $ticket->getId());
    }

    // ── Comments ─────────────────────────────────────────────────────

    public function addComment(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $body = is_array($data) && isset($data['body']) ? trim((string) $data['body']) : '';
        $author = $_SESSION['user']['username'] ?? 'Admin';

        if ($body !== '') {
            try {
                $this->tickets->addComment($ticket->getId(), $author, $body);
            } catch (Throwable $e) {
                // Silently ignore invalid comments
            }
        }

        return $this->redirectToShow($request, $response, $ticket->getId());
    }

    public function deleteComment(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->resolveTicket($request, $args);
        if ($ticket === null) {
            return $response->withStatus(404);
        }

        $commentId = isset($args['commentId']) ? (int) $args['commentId'] : 0;
        if ($commentId > 0) {
            $this->tickets->deleteComment($commentId);
        }

        return $this->redirectToShow($request, $response, $ticket->getId());
    }

    // ── Settings ─────────────────────────────────────────────────────

    public function settings(Request $request, Response $response): Response
    {
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $ticketSettings = $this->projectSettings->getTicketSettings($namespace);

        return Twig::fromRequest($request)->render($response, 'admin/tickets/settings.twig', [
            'ticketSettings' => $ticketSettings,
            'csrfToken' => $this->ensureCsrfToken(),
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'available_namespaces' => $availableNamespaces,
            'pageTab' => 'tickets',
        ]);
    }

    public function saveSettings(Request $request, Response $response): Response
    {
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $data = $request->getParsedBody();
        $publicSubmission = is_array($data) && !empty($data['ticket_public_submission']);

        try {
            $this->projectSettings->saveTicketSettings($namespace, $publicSubmission);
        } catch (Throwable $e) {
            // Redirect back regardless
        }

        return $this->redirectToList($request, $response, 'settings_saved');
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function renderForm(
        Request $request,
        Response $response,
        ?Ticket $ticket,
        ?array $override = null,
        ?string $error = null
    ): Response {
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        $payload = [
            'ticket' => $ticket,
            'error' => $error,
            'csrfToken' => $this->ensureCsrfToken(),
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'available_namespaces' => $availableNamespaces,
            'pageTab' => 'tickets',
        ];

        if ($override !== null) {
            $payload['override'] = $override;
        }

        return Twig::fromRequest($request)->render($response, 'admin/tickets/form.twig', $payload);
    }

    private function resolveTicket(Request $request, array $args): ?Ticket
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        $ticket = $this->tickets->getById($id);
        if ($ticket === null) {
            return null;
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if ($ticket->getNamespace() !== $namespace) {
            return null;
        }

        return $ticket;
    }

    private function redirectToList(Request $request, Response $response, ?string $status = null): Response
    {
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $query = [];
        if ($namespace !== '') {
            $query['namespace'] = $namespace;
        }
        if ($status !== null && $status !== '') {
            $query['status'] = $status;
        }
        $qs = http_build_query($query);

        return $response
            ->withHeader('Location', $basePath . '/admin/tickets' . ($qs !== '' ? '?' . $qs : ''))
            ->withStatus(303);
    }

    private function redirectToShow(Request $request, Response $response, int $ticketId): Response
    {
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $query = [];
        if ($namespace !== '') {
            $query['namespace'] = $namespace;
        }
        $qs = http_build_query($query);

        return $response
            ->withHeader('Location', $basePath . '/admin/tickets/' . $ticketId . ($qs !== '' ? '?' . $qs : ''))
            ->withStatus(303);
    }

    private function extractFormData(Request $request): array
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $labelsRaw = isset($data['labels']) ? trim((string) $data['labels']) : '';
        $labels = [];
        if ($labelsRaw !== '') {
            $labels = array_values(array_filter(
                array_map('trim', explode(',', $labelsRaw)),
                static fn (string $l): bool => $l !== ''
            ));
        }

        return [
            'title' => isset($data['title']) ? trim((string) $data['title']) : '',
            'description' => isset($data['description']) ? (string) $data['description'] : '',
            'type' => isset($data['type']) ? (string) $data['type'] : 'task',
            'priority' => isset($data['priority']) ? (string) $data['priority'] : 'normal',
            'assignee' => isset($data['assignee']) ? trim((string) $data['assignee']) : '',
            'labels' => $labels,
            'due_date' => isset($data['due_date']) ? trim((string) $data['due_date']) : '',
        ];
    }

    private function normalizeFlashStatus(?string $status): string
    {
        if ($status === null) {
            return '';
        }

        $value = trim($status);
        $allowed = ['created', 'updated', 'deleted', 'settings_saved'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function ensureCsrfToken(): string
    {
        $token = $_SESSION['csrf_token'] ?? '';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);
        $pdo = \App\Support\RequestDatabase::resolve($request);
        $repository = new NamespaceRepository($pdo);
        try {
            $availableNamespaces = $repository->listActive();
        } catch (\RuntimeException $exception) {
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
                'label' => 'label_namespace_not_saved',
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
                        'label' => 'label_namespace_not_saved',
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
}
