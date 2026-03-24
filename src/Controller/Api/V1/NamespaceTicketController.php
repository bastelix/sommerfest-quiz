<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\TicketService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NamespaceTicketController
{
    public const SCOPE_TICKET_READ = 'ticket:read';
    public const SCOPE_TICKET_WRITE = 'ticket:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?TicketService $tickets = null,
    ) {
    }

    /**
     * GET /api/v1/namespaces/{ns}/tickets
     */
    public function list(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->tickets ?? new TicketService($pdo);

        $queryParams = $request->getQueryParams();
        $filters = [];
        foreach (['status', 'priority', 'type', 'assignee', 'referenceType', 'referenceId'] as $key) {
            if (isset($queryParams[$key]) && is_string($queryParams[$key]) && $queryParams[$key] !== '') {
                $filters[$key] = $queryParams[$key];
            }
        }

        $items = [];
        foreach ($svc->listByNamespace($ns, $filters) as $ticket) {
            $items[] = $ticket->jsonSerialize();
        }

        return $this->json($response, ['namespace' => $ns, 'tickets' => $items]);
    }

    /**
     * GET /api/v1/namespaces/{ns}/tickets/{id}
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
        $svc = $this->tickets ?? new TicketService($pdo);

        $ticket = $svc->getById($id);
        if ($ticket === null || $ticket->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        return $this->json($response, ['namespace' => $ns, 'ticket' => $ticket->jsonSerialize()]);
    }

    /**
     * POST /api/v1/namespaces/{ns}/tickets
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

        $title = isset($payload['title']) && is_string($payload['title']) ? trim($payload['title']) : '';
        if ($title === '') {
            return $this->json($response, ['error' => 'missing_required_fields'], 422);
        }

        $description = isset($payload['description']) && is_string($payload['description'])
            ? $payload['description'] : '';
        $type = isset($payload['type']) && is_string($payload['type'])
            ? $payload['type'] : 'task';
        $priority = isset($payload['priority']) && is_string($payload['priority'])
            ? $payload['priority'] : 'normal';
        $referenceType = isset($payload['referenceType']) && is_string($payload['referenceType'])
            ? $payload['referenceType'] : null;
        $referenceId = isset($payload['referenceId']) && is_numeric($payload['referenceId'])
            ? (int) $payload['referenceId'] : null;
        $assignee = isset($payload['assignee']) && is_string($payload['assignee']) ? $payload['assignee'] : null;
        $labels = isset($payload['labels']) && is_array($payload['labels']) ? $payload['labels'] : [];
        $dueDate = isset($payload['dueDate']) && is_string($payload['dueDate']) ? $payload['dueDate'] : null;
        $createdBy = isset($payload['createdBy']) && is_string($payload['createdBy']) ? $payload['createdBy'] : null;

        $pdo = $this->resolvePdo($request);
        $svc = $this->tickets ?? new TicketService($pdo);

        try {
            $ticket = $svc->create(
                $ns,
                $title,
                $description,
                $type,
                $priority,
                $referenceType,
                $referenceId,
                $assignee,
                $labels,
                $dueDate,
                $createdBy,
            );
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        }

        return $this->json($response, [
            'status' => 'created',
            'ticket' => $ticket->jsonSerialize(),
        ], 201);
    }

    /**
     * PATCH /api/v1/namespaces/{ns}/tickets/{id}
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
        $svc = $this->tickets ?? new TicketService($pdo);

        $existing = $svc->getById($id);
        if ($existing === null || $existing->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        try {
            $ticket = $svc->update($id, $payload);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        }

        return $this->json($response, [
            'status' => 'updated',
            'ticket' => $ticket->jsonSerialize(),
        ]);
    }

    /**
     * PATCH /api/v1/namespaces/{ns}/tickets/{id}/status
     */
    public function transition(Request $request, Response $response, array $args): Response
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

        $newStatus = isset($payload['status']) && is_string($payload['status']) ? trim($payload['status']) : '';
        if ($newStatus === '') {
            return $this->json($response, ['error' => 'missing_status'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->tickets ?? new TicketService($pdo);

        $existing = $svc->getById($id);
        if ($existing === null || $existing->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        try {
            $ticket = $svc->transition($id, $newStatus);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => 'invalid_transition', 'message' => $e->getMessage()], 422);
        }

        return $this->json($response, [
            'status' => 'transitioned',
            'ticket' => $ticket->jsonSerialize(),
        ]);
    }

    /**
     * DELETE /api/v1/namespaces/{ns}/tickets/{id}
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
        $svc = $this->tickets ?? new TicketService($pdo);

        $existing = $svc->getById($id);
        if ($existing === null || $existing->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $svc->delete($id);

        return $this->json($response, ['status' => 'deleted']);
    }

    /**
     * GET /api/v1/namespaces/{ns}/tickets/{id}/comments
     */
    public function listComments(Request $request, Response $response, array $args): Response
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
        $svc = $this->tickets ?? new TicketService($pdo);

        $existing = $svc->getById($id);
        if ($existing === null || $existing->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $comments = [];
        foreach ($svc->listComments($id) as $comment) {
            $comments[] = $comment->jsonSerialize();
        }

        return $this->json($response, ['ticketId' => $id, 'comments' => $comments]);
    }

    /**
     * POST /api/v1/namespaces/{ns}/tickets/{id}/comments
     */
    public function addComment(Request $request, Response $response, array $args): Response
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

        $author = isset($payload['author']) && is_string($payload['author']) ? trim($payload['author']) : '';
        $body = isset($payload['body']) && is_string($payload['body']) ? trim($payload['body']) : '';
        if ($author === '' || $body === '') {
            return $this->json($response, ['error' => 'missing_required_fields'], 422);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->tickets ?? new TicketService($pdo);

        $existing = $svc->getById($id);
        if ($existing === null || $existing->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        try {
            $comment = $svc->addComment($id, $author, $body);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        }

        return $this->json($response, [
            'status' => 'created',
            'comment' => $comment->jsonSerialize(),
        ], 201);
    }

    /**
     * DELETE /api/v1/namespaces/{ns}/tickets/{id}/comments/{commentId}
     */
    public function deleteComment(Request $request, Response $response, array $args): Response
    {
        $ns = $this->requireNamespaceMatch($request, $args);
        if ($ns === null) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $id = (int) ($args['id'] ?? 0);
        $commentId = (int) ($args['commentId'] ?? 0);
        if ($id <= 0 || $commentId <= 0) {
            return $this->json($response, ['error' => 'invalid_id'], 400);
        }

        $pdo = $this->resolvePdo($request);
        $svc = $this->tickets ?? new TicketService($pdo);

        $existing = $svc->getById($id);
        if ($existing === null || $existing->getNamespace() !== $ns) {
            return $this->json($response, ['error' => 'not_found'], 404);
        }

        $svc->deleteComment($commentId);

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
