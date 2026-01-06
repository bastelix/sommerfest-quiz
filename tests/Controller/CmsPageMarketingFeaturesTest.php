<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Cms\PageController;
use App\Domain\Page;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class CmsPageMarketingFeaturesTest extends TestCase
{
    public function testResolvePageFeaturesMergesDefaultsAndConfig(): void
    {
        $controller = $this->createController();
        $page = new Page(1, 'default', 'landing', 'Landing', '{}', 'marketing', null, 0, null, null, null, null, false);

        $design = [
            'config' => [
                'pageTypes' => [
                    'marketing' => [
                        'features' => [
                            'contactTurnstile' => false,
                            'provenExpert' => true,
                            'laborAssets' => true,
                        ],
                    ],
                ],
            ],
        ];

        $features = $this->invokeResolvePageFeatures($controller, $page, 'landing', $design);

        $this->assertTrue($features['landingNews']);
        $this->assertFalse($features['contactTurnstile']);
        $this->assertTrue($features['provenExpert']);
        $this->assertTrue($features['laborAssets']);
    }

    public function testEnsureTurnstileMarkupAddsWidgetToContactForm(): void
    {
        $controller = $this->createController();
        $html = '<form id="contact-form"><div data-turnstile-container></div></form>';

        $result = $this->invokeEnsureTurnstileMarkup($controller, $html, '<div class="cf-turnstile"></div>', false);

        $this->assertStringContainsString('cf-turnstile', $result);
    }

    private function createController(): PageController
    {
        $reflection = new ReflectionClass(PageController::class);

        /** @var PageController $controller */
        $controller = $reflection->newInstanceWithoutConstructor();

        return $controller;
    }

    /**
     * @param array<string, mixed> $design
     * @return array<string, bool>
     */
    private function invokeResolvePageFeatures(PageController $controller, Page $page, string $slug, array $design): array
    {
        $method = new ReflectionMethod(PageController::class, 'resolvePageFeatures');
        $method->setAccessible(true);

        /** @var array<string, bool> $result */
        $result = $method->invoke($controller, $page, $slug, $design);

        return $result;
    }

    private function invokeEnsureTurnstileMarkup(
        PageController $controller,
        string $html,
        string $widget,
        bool $placeholder
    ): string {
        $method = new ReflectionMethod(PageController::class, 'ensureTurnstileMarkup');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $html, $widget, $placeholder);
    }
}
