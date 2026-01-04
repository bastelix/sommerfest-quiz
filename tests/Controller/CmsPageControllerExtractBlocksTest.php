<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Cms\PageController;
use App\Service\CmsPageMenuService;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceResolver;
use App\Service\PageContentLoader;
use App\Service\PageModuleService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Application\Seo\PageSeoConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Tests\TestCase;

#[CoversClass(PageController::class)]
class CmsPageControllerExtractBlocksTest extends TestCase
{
    public function testExtractPageBlocksHandlesFlexibleScriptAttributes(): void
    {
        $controller = new PageController(
            null,
            $this->createMock(PageService::class),
            $this->createMock(PageSeoConfigService::class),
            $this->createMock(PageContentLoader::class),
            $this->createMock(PageModuleService::class),
            $this->createMock(NamespaceAppearanceService::class),
            $this->createMock(NamespaceResolver::class),
            $this->createMock(ProjectSettingsService::class),
            $this->createMock(ConfigService::class),
            $this->createMock(EffectsPolicyService::class),
            $this->createMock(CmsPageMenuService::class)
        );

        $html = <<<HTML
            <div>content</div>
            <script data-page-slug="landing" type="application/json" data-json="page" data-page-namespace="default">
                {"blocks": [{"type": "text", "data": {"text": "Hello"}}]}
            </script>
            HTML;

        $method = new ReflectionMethod(PageController::class, 'extractPageBlocks');
        $method->setAccessible(true);

        $blocks = $method->invoke($controller, $html);

        $this->assertSame([
            ['type' => 'text', 'data' => ['text' => 'Hello']],
        ], $blocks);
    }
}
