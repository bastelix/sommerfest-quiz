<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class FaqControllerTest extends TestCase
{
    public function testFaqPage(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/faq');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
