<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Marketing\CmsPageController;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class MarketingCmsPageControllerTest extends TestCase
{
    public function testAcceptHeaderDoesNotTriggerJsonResponse(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $request = $this->createRequest('GET', '/m/sample', ['HTTP_ACCEPT' => 'application/json']);

        $this->assertFalse($this->invokeWantsJson($controller, $request));
    }

    public function testExplicitFlagsStillReturnJson(): void
    {
        $controller = $this->createControllerWithoutConstructor();

        $formatRequest = $this->createRequest('GET', '/m/sample?format=json');
        $this->assertTrue($this->invokeWantsJson($controller, $formatRequest));

        $flagRequest = $this->createRequest('GET', '/m/sample?json=1');
        $this->assertTrue($this->invokeWantsJson($controller, $flagRequest));
    }

    private function createControllerWithoutConstructor(): CmsPageController
    {
        $reflection = new ReflectionClass(CmsPageController::class);

        /** @var CmsPageController $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        return $instance;
    }

    private function invokeWantsJson(CmsPageController $controller, ServerRequestInterface $request): bool
    {
        $method = new ReflectionMethod(CmsPageController::class, 'wantsJson');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $request);
    }
}
