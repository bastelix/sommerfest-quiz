<?php

declare(strict_types=1);

namespace Tests\Application;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class OptionsRequestTest extends TestCase
{
    public function testOptionsRequestReturnsNoContent(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest(
            'OPTIONS',
            '/onboarding/tenants/example',
            [
                'Origin' => 'https://example.com',
                'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'X-Requested-With',
            ]
        );

        $response = $app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }
}
