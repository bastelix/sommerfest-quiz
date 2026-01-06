<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Marketing\CmsPageController;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\NamespaceAppearanceService;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
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

    public function testLoadDesignFailsWhenNamespaceConfigMissing(): void
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->expects($this->once())
            ->method('getConfigForEvent')
            ->with('tenant')
            ->willReturn([]);

        $namespaceAppearance = $this->createMock(NamespaceAppearanceService::class);
        $namespaceAppearance->expects($this->never())->method('load');

        $effectsPolicy = $this->createMock(EffectsPolicyService::class);
        $effectsPolicy->expects($this->never())->method('getEffectsForNamespace');

        $controller = $this->createControllerWithDesignServices(
            $configService,
            $namespaceAppearance,
            $effectsPolicy,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No design configuration found for namespace: tenant');

        $this->invokeLoadDesign($controller, 'tenant');
    }

    private function createControllerWithoutConstructor(): CmsPageController
    {
        $reflection = new ReflectionClass(CmsPageController::class);

        /** @var CmsPageController $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        return $instance;
    }

    private function createControllerWithDesignServices(
        ConfigService $configService,
        NamespaceAppearanceService $namespaceAppearance,
        EffectsPolicyService $effectsPolicy
    ): CmsPageController {
        $controller = $this->createControllerWithoutConstructor();

        $this->setPrivateProperty($controller, 'configService', $configService);
        $this->setPrivateProperty($controller, 'namespaceAppearance', $namespaceAppearance);
        $this->setPrivateProperty($controller, 'effectsPolicy', $effectsPolicy);

        return $controller;
    }

    private function invokeLoadDesign(CmsPageController $controller, string $namespace): array
    {
        $method = new ReflectionMethod(CmsPageController::class, 'loadDesign');
        $method->setAccessible(true);

        /** @var array $result */
        $result = $method->invoke($controller, $namespace);

        return $result;
    }

    private function invokeWantsJson(CmsPageController $controller, ServerRequestInterface $request): bool
    {
        $method = new ReflectionMethod(CmsPageController::class, 'wantsJson');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $request);
    }

    private function setPrivateProperty(object $controller, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(CmsPageController::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($controller, $value);
    }
}
