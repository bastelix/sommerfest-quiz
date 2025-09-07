<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\UserService;
use App\Domain\Roles;
use Tests\TestCase;

final class LoginRedirectMainDomainTest extends TestCase
{
    public function testLoginRedirectsToMainDomainOnWrongHost(): void
    {
        $pdo = $this->getDatabase();
        $userService = new UserService($pdo);
        $userService->create('heidi', 'secret', 'heidi@example.com', Roles::ADMIN);

        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['csrf_token'] = 'tok';
        $_SERVER['HTTP_HOST'] = 'tenant.main.test';
        $request = $this->createRequest('POST', '/login', ['HTTP_HOST' => 'tenant.main.test'])
            ->withParsedBody([
                'username' => 'heidi',
                'password' => 'secret',
                'csrf_token' => 'tok',
            ]);
        $request = $request->withUri(
            $request->getUri()->withHost('tenant.main.test')->withScheme('https')
        );
        $response = $app->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://main.test/admin', $response->getHeaderLine('Location'));

        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN'], $_SERVER['HTTP_HOST']);
    }
}
