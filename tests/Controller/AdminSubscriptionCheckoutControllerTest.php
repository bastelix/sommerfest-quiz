<?php

declare(strict_types=1);

namespace Tests\Controller;

use Slim\Psr7\Factory\StreamFactory;
use Tests\TestCase;

final class AdminSubscriptionCheckoutControllerTest extends TestCase
{
    public function testCheckoutUsesProfileOnMainDomain(): void {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $request = $this->createRequest('POST', '/admin/subscription/checkout', [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'token',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode(['plan' => 'starter']));
        $request = $request->withBody($stream);
        $response = $app->handle($request);
        $this->assertSame(503, $response->getStatusCode());
    }
}
