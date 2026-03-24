<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Service\Mcp\McpToolRegistry;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class McpController
{
    private const PROTOCOL_VERSION = '2025-03-26';
    private const SERVER_NAME = 'sommerfest-quiz';
    private const SERVER_VERSION = '1.0.0';

    /**
     * POST /mcp — Streamable HTTP transport (JSON-RPC 2.0)
     */
    public function handle(Request $request, Response $response): Response
    {
        $body = (string) $request->getBody();
        $rpc = json_decode($body, true);

        if (!is_array($rpc) || !isset($rpc['jsonrpc']) || $rpc['jsonrpc'] !== '2.0') {
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

        return match ($method) {
            'initialize' => $this->handleInitialize($request, $response, $id, $params),
            'tools/list' => $this->handleToolsList($request, $response, $id),
            'tools/call' => $this->handleToolsCall($request, $response, $id, $params),
            default => $this->jsonRpcError($response, $id, -32601, 'Method not found: ' . $method),
        };
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

    private function handleToolsList(Request $request, Response $response, mixed $id): Response
    {
        $registry = $this->getRegistry($request);
        $tools = $registry->listTools();

        return $this->jsonRpcResult($response, $id, ['tools' => $tools]);
    }

    private function handleToolsCall(Request $request, Response $response, mixed $id, array $params): Response
    {
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];

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
            return $this->jsonRpcResult($response, $id, [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'error' => 'missing_scope',
                        'required' => $requiredScope,
                    ]),
                ]],
                'isError' => true,
            ]);
        }

        $registry = $this->getRegistry($request);
        $result = $registry->callTool($name, $arguments);

        return $this->jsonRpcResult($response, $id, $result);
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
            'list_menus', 'list_menu_items' => 'menu:read',
            'create_menu', 'update_menu', 'delete_menu',
            'create_menu_item', 'update_menu_item', 'delete_menu_item' => 'menu:write',
            'list_news', 'get_news' => 'news:read',
            'create_news', 'update_news', 'delete_news' => 'news:write',
            'list_footer_blocks', 'get_footer_layout' => 'footer:read',
            'create_footer_block', 'update_footer_block', 'delete_footer_block',
            'reorder_footer_blocks', 'update_footer_layout' => 'footer:write',
            'list_events', 'get_event', 'list_catalogs', 'get_catalog',
            'list_results', 'list_teams' => 'quiz:read',
            'upsert_catalog', 'submit_result' => 'quiz:write',
            'export_namespace' => 'backup:read',
            'import_namespace' => 'backup:write',
            'get_design_tokens', 'get_custom_css', 'list_design_presets', 'get_design_schema' => 'design:read',
            'update_design_tokens', 'update_custom_css', 'import_design_preset', 'reset_design' => 'design:write',
            'get_wiki_settings', 'list_wiki_articles', 'get_wiki_article', 'get_wiki_article_versions' => 'wiki:read',
            'update_wiki_settings', 'create_wiki_article', 'update_wiki_article', 'delete_wiki_article',
            'update_wiki_article_status', 'reorder_wiki_articles' => 'wiki:write',
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
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonRpcError(Response $response, mixed $id, int $code, string $message): Response
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
