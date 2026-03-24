<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class ImpressumControllerTest extends TestCase
{
    public function testImpressumPage(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/impressum');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
