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
     * POST /mcp — JSON-RPC 2.0 endpoint for MCP protocol
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

        return match ($method) {
            'initialize' => $this->handleInitialize($response, $id, $params),
            'notifications/initialized' => $this->handleNotification($response),
            'tools/list' => $this->handleToolsList($request, $response, $id),
            'tools/call' => $this->handleToolsCall($request, $response, $id, $params),
            default => $this->jsonRpcError($response, $id, -32601, 'Method not found: ' . $method),
        };
    }

    private function handleInitialize(Response $response, mixed $id, array $params): Response
    {
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

        return $this->jsonRpcResult($response, $id, $result);
    }

    private function handleNotification(Response $response): Response
    {
        // Notifications have no response body
        return $response->withStatus(204);
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
                'content' => [['type' => 'text', 'text' => json_encode(['error' => 'missing_scope', 'required' => $requiredScope])]],
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
            'list_pages', 'get_page_tree' => 'cms:read',
            'upsert_page' => 'cms:write',
            'list_menus', 'list_menu_items' => 'menu:read',
            'create_menu', 'update_menu', 'delete_menu',
            'create_menu_item', 'update_menu_item', 'delete_menu_item' => 'menu:write',
            'list_news', 'get_news' => 'news:read',
            'create_news', 'update_news', 'delete_news' => 'news:write',
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
