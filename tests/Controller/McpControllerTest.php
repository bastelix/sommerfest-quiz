<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Controller\Api\McpController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class McpControllerTest extends TestCase
{
    private function createJsonRpcRequest(
        mixed $body,
        array $attributes = [],
        bool $withSession = true
    ): Request {
        $uri = new Uri('https', 'example.com', 443, '/mcp');
        $stream = (new StreamFactory())->createStream(
            is_string($body) ? $body : json_encode($body)
        );
        $headers = new Headers();
        $headers->addHeader('Content-Type', 'application/json');
        $headers->addHeader('Accept', 'application/json, text/event-stream');
        if ($withSession) {
            $headers->addHeader('Mcp-Session-Id', 'test-session-id-abc123');
        }

        $request = new Request('POST', $uri, $headers, [], [], $stream);

        foreach ($attributes as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    private function decodeResponse(Response $response): array
    {
        $response->getBody()->rewind();
        return json_decode((string) $response->getBody(), true);
    }

    // ---------------------------------------------------------------
    // Ping
    // ---------------------------------------------------------------

    public function testPingReturnsEmptyResult(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ]);

        $response = $controller->handle($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertSame(1, $data['id']);
        $this->assertSame([], $data['result']);
    }

    // ---------------------------------------------------------------
    // JSON-RPC basics
    // ---------------------------------------------------------------

    public function testInvalidJsonReturnsParseError(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest('not valid json{{{');

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertSame(-32700, $data['error']['code']);
    }

    public function testMissingJsonrpcFieldReturnsInvalidRequest(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            ['method' => 'ping', 'id' => 1]
        );

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertSame(-32600, $data['error']['code']);
    }

    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'nonexistent/method',
        ]);

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertSame(-32601, $data['error']['code']);
    }

    public function testNotificationReturns202(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $response = $controller->handle($request, new Response());

        $this->assertSame(202, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // Session-ID validation
    // ---------------------------------------------------------------

    public function testMissingSessionIdReturns400(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            [],
            false // no session header
        );

        $response = $controller->handle($request, new Response());

        $this->assertSame(400, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertStringContainsString(
            'Mcp-Session-Id',
            $data['error']['message']
        );
    }

    public function testInitializeDoesNotRequireSessionId(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ],
            [],
            false // no session header - initialize should still work
        );

        $response = $controller->handle($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty(
            $response->getHeaderLine('Mcp-Session-Id')
        );
    }

    public function testBatchWithoutSessionIdReturns400(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            [
                ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
                ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
            ],
            [],
            false
        );

        $response = $controller->handle($request, new Response());

        $this->assertSame(400, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // Batch requests
    // ---------------------------------------------------------------

    public function testBatchWithMultiplePingsReturnsArray(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
        ]);

        $response = $controller->handle($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertCount(2, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame(2, $data[1]['id']);
        $this->assertSame([], $data[0]['result']);
        $this->assertSame([], $data[1]['result']);
    }

    public function testBatchWithOnlyNotificationsReturns202(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/cancelled'],
        ]);

        $response = $controller->handle($request, new Response());

        $this->assertSame(202, $response->getStatusCode());
    }

    public function testBatchRejectsInitializeInBatch(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [],
            ],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
        ]);

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertSame(-32600, $data['error']['code']);
        $this->assertStringContainsString(
            'initialize',
            $data['error']['message']
        );
    }

    public function testBatchMixedRequestsAndNotifications(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            [
                'jsonrpc' => '2.0',
                'method' => 'notifications/initialized',
            ],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
        ]);

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertCount(2, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame(2, $data[1]['id']);
    }

    public function testEmptyBatchArrayReturnsInvalidRequest(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest([]);

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertSame(-32600, $data['error']['code']);
    }

    // ---------------------------------------------------------------
    // HTTP 403 for missing scopes
    // ---------------------------------------------------------------

    public function testToolsCallWithoutRequiredScopeReturns403(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'list_pages',
                    'arguments' => [],
                ],
            ],
            [
                ApiTokenAuthMiddleware::ATTR_TOKEN_SCOPES => ['news:read'],
                ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE => 'test',
            ]
        );

        $response = $controller->handle($request, new Response());

        $this->assertSame(403, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString(
            'cms:read',
            $data['error']['message']
        );
    }

    public function testToolsCallMissingToolNameReturnsError(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['arguments' => []],
            ],
            [
                ApiTokenAuthMiddleware::ATTR_TOKEN_SCOPES => [],
                ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE => 'test',
            ]
        );

        $response = $controller->handle($request, new Response());

        $data = $this->decodeResponse($response);
        $this->assertSame(-32602, $data['error']['code']);
    }

    // ---------------------------------------------------------------
    // Scope mapping completeness
    // ---------------------------------------------------------------

    /**
     * @dataProvider scopeMappingProvider
     */
    public function testScopeMappingIsComplete(
        string $toolName,
        string $expectedScope
    ): void {
        $controller = new McpController();

        $method = new \ReflectionMethod($controller, 'getRequiredScope');
        $method->setAccessible(true);

        $scope = $method->invoke($controller, $toolName);
        $this->assertSame(
            $expectedScope,
            $scope,
            "Tool '{$toolName}' should require scope '{$expectedScope}'"
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function scopeMappingProvider(): array
    {
        return [
            // Menu assignments
            'list_menu_assignments' => ['list_menu_assignments', 'menu:read'],
            'create_menu_assignment' => ['create_menu_assignment', 'menu:write'],
            'update_menu_assignment' => ['update_menu_assignment', 'menu:write'],
            'delete_menu_assignment' => ['delete_menu_assignment', 'menu:write'],

            // News categories
            'list_news_categories' => ['list_news_categories', 'news:read'],
            'create_news_category' => ['create_news_category', 'news:write'],
            'delete_news_category' => ['delete_news_category', 'news:write'],
            'assign_news_category' => ['assign_news_category', 'news:write'],
            'remove_news_category' => ['remove_news_category', 'news:write'],

            // Design tools
            'get_design_manifest' => ['get_design_manifest', 'design:read'],
            'validate_page_design' => ['validate_page_design', 'design:read'],

            // Existing mappings - spot check
            'list_pages' => ['list_pages', 'cms:read'],
            'upsert_page' => ['upsert_page', 'cms:write'],
            'list_menus' => ['list_menus', 'menu:read'],
            'create_menu' => ['create_menu', 'menu:write'],
            'list_news' => ['list_news', 'news:read'],
            'create_news' => ['create_news', 'news:write'],
            'list_footer_blocks' => ['list_footer_blocks', 'footer:read'],
            'list_events' => ['list_events', 'quiz:read'],
            'export_namespace' => ['export_namespace', 'backup:read'],
            'get_design_tokens' => ['get_design_tokens', 'design:read'],
            'list_wiki_articles' => ['list_wiki_articles', 'wiki:read'],
            'list_tickets' => ['list_tickets', 'ticket:read'],
            'create_ticket' => ['create_ticket', 'ticket:write'],
        ];
    }

    public function testListNamespacesHasNoScope(): void
    {
        $controller = new McpController();
        $method = new \ReflectionMethod($controller, 'getRequiredScope');
        $method->setAccessible(true);

        $this->assertNull(
            $method->invoke($controller, 'list_namespaces')
        );
    }

    // ---------------------------------------------------------------
    // tools/list pagination
    // ---------------------------------------------------------------

    public function testToolsListPaginationConstantExists(): void
    {
        $ref = new \ReflectionClass(McpController::class);
        $this->assertTrue(
            $ref->hasConstant('TOOLS_PAGE_SIZE'),
            'TOOLS_PAGE_SIZE constant must exist'
        );
        $this->assertIsInt($ref->getConstant('TOOLS_PAGE_SIZE'));
    }

    // ---------------------------------------------------------------
    // GET /mcp
    // ---------------------------------------------------------------

    public function testGetReturnsServerInfo(): void
    {
        $controller = new McpController();
        $uri = new Uri('https', 'example.com', 443, '/mcp');
        $stream = (new StreamFactory())->createStream('');
        $headers = new Headers();
        $headers->addHeader('Accept', 'application/json');
        $request = new Request('GET', $uri, $headers, [], [], $stream);

        $response = $controller->handleGet($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $response->getBody()->rewind();
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('edocs-cloud', $data['name']);
        $this->assertSame('ok', $data['status']);
    }

    public function testGetWithSseAcceptReturnsEventStream(): void
    {
        $controller = new McpController();
        $uri = new Uri('https', 'example.com', 443, '/mcp');
        $stream = (new StreamFactory())->createStream('');
        $headers = new Headers();
        $headers->addHeader('Accept', 'text/event-stream');
        $request = new Request('GET', $uri, $headers, [], [], $stream);

        $response = $controller->handleGet($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'text/event-stream',
            $response->getHeaderLine('Content-Type')
        );
    }

    // ---------------------------------------------------------------
    // Initialize
    // ---------------------------------------------------------------

    public function testInitializeReturnsSessionId(): void
    {
        $controller = new McpController();
        $request = $this->createJsonRpcRequest(
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => [],
                    'clientInfo' => [
                        'name' => 'test',
                        'version' => '1.0',
                    ],
                ],
            ],
            [],
            false // initialize doesn't need session
        );

        $response = $controller->handle($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty(
            $response->getHeaderLine('Mcp-Session-Id')
        );
        $data = $this->decodeResponse($response);
        $this->assertSame(
            '2025-03-26',
            $data['result']['protocolVersion']
        );
        $this->assertSame(
            'edocs-cloud',
            $data['result']['serverInfo']['name']
        );
    }
}
