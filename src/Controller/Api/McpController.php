<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\Mcp\McpToolRegistry;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;

final class McpController
{
    private const PROTOCOL_VERSION = '2025-03-26';
    private const SERVER_NAME = 'edocs-cloud';
    private const SERVER_VERSION = '1.0.0';
    private const TOOLS_PAGE_SIZE = 50;

    /**
     * POST /mcp — Streamable HTTP transport (JSON-RPC 2.0)
     */
    public function handle(Request $request, Response $response): Response
    {
        $body = (string) $request->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->jsonRpcError($response, null, -32700, 'Parse error');
        }

        // Batch detection: numeric array = batch of messages
        if (array_is_list($decoded) && $decoded !== []) {
            return $this->handleBatch($request, $response, $decoded);
        }

        return $this->handleSingleMessage($request, $response, $decoded);
    }

    /**
     * GET /mcp — Return server info for quick connectivity checks.
     */
    public function handleGet(Request $request, Response $response): Response
    {
        $accept = $request->getHeaderLine('Accept');

        // Return an empty SSE stream for clients that probe for SSE support.
        // The spec says GET+SSE is optional; this satisfies clients without errors.
        if (str_contains($accept, 'text/event-stream')) {
            $response->getBody()->write(":\n\n");
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'text/event-stream')
                ->withHeader('Cache-Control', 'no-cache');
        }

        // Return server info for browser / health checks
        $payload = [
            'name' => self::SERVER_NAME,
            'version' => self::SERVER_VERSION,
            'protocolVersion' => self::PROTOCOL_VERSION,
            'status' => 'ok',
        ];
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /mcp — Session termination. Accept and invalidate.
     */
    public function handleDelete(Request $request, Response $response): Response
    {
        // Accept session termination request
        return $response->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Handle a JSON-RPC batch (array of messages).
     *
     * @param list<mixed> $batch
     */
    private function handleBatch(Request $request, Response $response, array $batch): Response
    {
        // Spec: initialize MUST NOT be part of a batch
        foreach ($batch as $entry) {
            if (is_array($entry) && ($entry['method'] ?? '') === 'initialize') {
                return $this->jsonRpcError(
                    $response,
                    $entry['id'] ?? null,
                    -32600,
                    'initialize must not be part of a batch'
                );
            }
        }

        // Validate session for non-initialize batches
        $sessionError = $this->validateSession($request, $response);
        if ($sessionError !== null) {
            return $sessionError;
        }

        $results = [];
        $hasRequests = false;

        foreach ($batch as $entry) {
            if (!is_array($entry)) {
                $results[] = [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => ['code' => -32600, 'message' => 'Invalid Request'],
                ];
                $hasRequests = true;
                continue;
            }

            $id = $entry['id'] ?? null;
            $method = isset($entry['method']) && is_string($entry['method']) ? $entry['method'] : '';
            $isNotification = $id === null && $method !== '';
            $isResponse = isset($entry['result']) || isset($entry['error']);

            // Notifications and responses produce no output
            if ($isNotification || $isResponse) {
                continue;
            }

            // This is a request (has id) — process and collect result
            $hasRequests = true;
            $singleResponse = new SlimResponse();
            $singleResponse = $this->handleSingleMessage($request, $singleResponse, $entry);

            $responseBody = (string) $singleResponse->getBody();
            $parsed = json_decode($responseBody, true);
            if (is_array($parsed)) {
                $results[] = $parsed;
            }
        }

        // If only notifications/responses → 202 Accepted
        if (!$hasRequests) {
            return $response->withStatus(202);
        }

        $json = (string) json_encode(
            $results,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Handle a single JSON-RPC message (request, notification, or response).
     */
    private function handleSingleMessage(Request $request, Response $response, array $rpc): Response
    {
        if (!isset($rpc['jsonrpc']) || $rpc['jsonrpc'] !== '2.0') {
            return $this->jsonRpcError($response, null, -32600, 'Invalid Request');
        }

        $id = $rpc['id'] ?? null;
        $method = isset($rpc['method']) && is_string($rpc['method']) ? $rpc['method'] : '';
        $params = isset($rpc['params']) && is_array($rpc['params']) ? $rpc['params'] : [];

        // Notifications and responses have no 'id' — return 202 Accepted per spec
        $isNotification = $id === null && $method !== '';
        $isResponse = isset($rpc['result']) || isset($rpc['error']);

        if ($isNotification || $isResponse) {
            return $this->handleNotificationOrResponse($request, $response, $method, $params);
        }

        // Session validation: all methods except initialize require a valid session
        if ($method !== 'initialize') {
            $sessionError = $this->validateSession($request, $response);
            if ($sessionError !== null) {
                return $sessionError;
            }
        }

        return match ($method) {
            'initialize' => $this->handleInitialize($request, $response, $id, $params),
            'ping' => $this->jsonRpcResult($response, $id, []),
            'tools/list' => $this->handleToolsList($request, $response, $id, $params),
            'tools/call' => $this->handleToolsCall($request, $response, $id, $params),
            default => $this->jsonRpcError($response, $id, -32601, 'Method not found: ' . $method),
        };
    }

    private function handleInitialize(Request $request, Response $response, mixed $id, array $params): Response
    {
        $sessionId = bin2hex(random_bytes(32));

        $result = [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];

        return $this->jsonRpcResult($response, $id, $result)
            ->withHeader('Mcp-Session-Id', $sessionId);
    }

    private function handleNotificationOrResponse(
        Request $request,
        Response $response,
        string $method,
        array $params
    ): Response {
        // Spec: notifications and responses → 202 Accepted, no body
        return $response->withStatus(202);
    }

    private function handleToolsList(
        Request $request,
        Response $response,
        mixed $id,
        array $params
    ): Response {
        $registry = $this->getRegistry($request);
        $allTools = $registry->listTools();

        $cursor = isset($params['cursor']) && is_string($params['cursor'])
            ? $params['cursor']
            : null;

        $offset = 0;
        if ($cursor !== null) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false && str_starts_with($decoded, 'offset:')) {
                $offset = max(0, (int) substr($decoded, 7));
            }
        }

        $page = array_slice($allTools, $offset, self::TOOLS_PAGE_SIZE);
        $nextOffset = $offset + self::TOOLS_PAGE_SIZE;

        $result = ['tools' => $page];
        if ($nextOffset < count($allTools)) {
            $result['nextCursor'] = base64_encode('offset:' . $nextOffset);
        }

        return $this->jsonRpcResult($response, $id, $result);
    }

    private function handleToolsCall(
        Request $request,
        Response $response,
        mixed $id,
        array $params
    ): Response {
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        $arguments = isset($params['arguments']) && is_array($params['arguments'])
            ? $params['arguments']
            : [];

        if ($name === '') {
            return $this->jsonRpcError($response, $id, -32602, 'Missing tool name');
        }

        // Scope check
        $scopes = $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_SCOPES);
        if (!is_array($scopes)) {
            $scopes = [];
        }

        $requiredScope = $this->getRequiredScope($name);
        if ($requiredScope !== null && !in_array($requiredScope, $scopes, true)) {
            return $this->jsonRpcForbidden($response, $id, $requiredScope);
        }

        $registry = $this->getRegistry($request);
        $result = $registry->callTool($name, $arguments);

        return $this->jsonRpcResult($response, $id, $result);
    }

    /**
     * Validate the Mcp-Session-Id header is present on non-initialize requests.
     *
     * Per MCP spec: "Servers that require a session ID SHOULD respond to requests
     * without an Mcp-Session-Id header (other than initialization) with HTTP 400."
     *
     * Returns an error response if invalid, or null if OK.
     */
    private function validateSession(Request $request, Response $response): ?Response
    {
        $sessionId = $request->getHeaderLine('Mcp-Session-Id');
        if ($sessionId === '') {
            return $this->jsonRpcError($response, null, -32600, 'Missing Mcp-Session-Id header')
                ->withStatus(400);
        }

        return null;
    }

    private function getRegistry(Request $request): McpToolRegistry
    {
        $pdo = RequestDatabase::resolve($request);
        $namespace = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        return new McpToolRegistry($pdo, $namespace);
    }

    private function getRequiredScope(string $toolName): ?string
    {
        return match ($toolName) {
            'list_pages', 'get_page_tree', 'get_block_contract' => 'cms:read',
            'upsert_page', 'delete_page' => 'cms:write',
            'list_menus', 'list_menu_items',
            'list_menu_assignments' => 'menu:read',
            'create_menu', 'update_menu', 'delete_menu',
            'create_menu_item', 'update_menu_item', 'delete_menu_item',
            'create_menu_assignment', 'update_menu_assignment',
            'delete_menu_assignment' => 'menu:write',
            'list_news', 'get_news',
            'list_news_categories' => 'news:read',
            'create_news', 'update_news', 'delete_news',
            'create_news_category', 'delete_news_category',
            'assign_news_category',
            'remove_news_category' => 'news:write',
            'list_footer_blocks', 'get_footer_layout' => 'footer:read',
            'create_footer_block', 'update_footer_block',
            'delete_footer_block', 'reorder_footer_blocks',
            'update_footer_layout' => 'footer:write',
            'list_events', 'get_event', 'list_catalogs',
            'get_catalog', 'list_results',
            'list_teams' => 'quiz:read',
            'upsert_catalog', 'submit_result' => 'quiz:write',
            'export_namespace' => 'backup:read',
            'import_namespace' => 'backup:write',
            'get_design_tokens', 'get_custom_css',
            'list_design_presets', 'get_design_schema',
            'get_design_manifest',
            'validate_page_design' => 'design:read',
            'update_design_tokens', 'update_custom_css',
            'import_design_preset',
            'reset_design' => 'design:write',
            'get_wiki_settings', 'list_wiki_articles',
            'get_wiki_article',
            'get_wiki_article_versions' => 'wiki:read',
            'update_wiki_settings', 'create_wiki_article',
            'update_wiki_article', 'delete_wiki_article',
            'update_wiki_article_status',
            'reorder_wiki_articles' => 'wiki:write',
            'list_tickets', 'get_ticket',
            'list_ticket_comments' => 'ticket:read',
            'create_ticket', 'update_ticket',
            'transition_ticket', 'delete_ticket',
            'add_ticket_comment',
            'delete_ticket_comment' => 'ticket:write',
            // list_namespaces is intentionally public (discovery tool)
            default => null,
        };
    }

    private function jsonRpcResult(Response $response, mixed $id, mixed $result): Response
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
        $json = (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonRpcError(Response $response, mixed $id, int $code, string $message): Response
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
        $json = (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonRpcForbidden(Response $response, mixed $id, string $scope): Response
    {
        return $this->jsonRpcError(
            $response,
            $id,
            -32600,
            'Forbidden: missing required scope: ' . $scope
        )->withStatus(403);
    }
}
