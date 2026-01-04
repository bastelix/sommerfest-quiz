<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\Marketing\CmsPageController;
use App\Domain\CmsPageWikiSettings;
use App\Domain\Page;
use App\Application\Seo\PageSeoConfigService;
use App\Service\CmsPageMenuService;
use App\Service\CmsPageWikiArticleService;
use App\Service\CmsPageWikiSettingsService;
use App\Service\ConfigService;
use App\Service\EffectsPolicyService;
use App\Service\LandingNewsService;
use App\Service\NamespaceAppearanceService;
use App\Service\NamespaceContext;
use App\Service\NamespaceResolver;
use App\Service\NamespaceService;
use App\Service\NamespaceValidator;
use App\Service\PageContentLoader;
use App\Service\PageModuleService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Service\ProvenExpertRatingService;
use App\Service\DesignTokenService;
use App\Service\TurnstileConfig;
use App\Service\Marketing\MarketingMenuAiGenerator;
use App\Service\Marketing\MarketingMenuAiTranslator;
use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use App\Repository\NamespaceRepository;
use App\Infrastructure\Database;
use App\Service\TranslationService;
use App\Twig\DateTimeFormatExtension;
use App\Twig\TranslationExtension;
use App\Twig\UikitExtension;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Tests\TestCase;
use PDO;
use ReflectionProperty;

class CmsPageNamespaceDesignTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Database::setFactory(null);
    }

    public function testJsonPayloadUsesResolvedNamespaceForDesign(): void
    {
        $controller = $this->createController('tenant-space', 'default');
        [$app, $request] = $this->buildAppWithController($controller, '/styled?format=json', ['HTTP_ACCEPT' => 'application/json']);

        $response = $app->handle($request->withAttribute('lang', 'de'));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame('tenant-space', $payload['namespace']);
        $this->assertSame('default', $payload['contentNamespace']);
        $this->assertSame('tenant-space', $payload['design']['namespace']);
    }

    public function testHtmlUsesResolvedNamespaceInDataAttribute(): void
    {
        $controller = $this->createController('tenant-space', 'default');
        [$app, $request] = $this->buildAppWithController($controller, '/styled');

        $response = $app->handle($request->withAttribute('lang', 'de'));

        $this->assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        $this->assertStringContainsString('data-namespace="tenant-space"', $html);
    }

    /**
     * @return array{0: \Slim\App, 1: \Psr\Http\Message\ServerRequestInterface}
     */
    private function buildAppWithController(CmsPageController $controller, string $path, array $headers = ['HTTP_ACCEPT' => 'text/html']): array
    {
        $app = AppFactory::create();
        $twig = Twig::create(__DIR__ . '/../../templates', ['cache' => false]);
        $translator = new TranslationService();
        $twig->addExtension(new UikitExtension());
        $twig->addExtension(new DateTimeFormatExtension());
        $twig->addExtension(new TranslationExtension($translator));
        $app->add(TwigMiddleware::create($app, $twig));

        $app->get('/styled', function ($request, $response) use ($controller) {
            return $controller($request, $response, ['slug' => 'styled']);
        });

        $request = $this->createRequest('GET', $path, $headers)
            ->withAttribute('namespace', 'tenant-space');

        return [$app, $request];
    }

    private function createController(string $resolvedNamespace, string $contentNamespace): CmsPageController
    {
        $page = new Page(1, $contentNamespace, 'styled', 'Styled', '<p>Styled</p>', null, null, 0, null, null, null, null, false);

        $pageService = $this->createMock(PageService::class);
        $pageService->method('findByKey')->willReturn($page);

        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfigForEvent')->willReturnCallback(static fn (string $namespace): array => ['namespace' => $namespace]);
        $configService->method('ensureConfigForEvent')->willReturnCallback(static function (): void {
        });

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE namespaces (namespace TEXT PRIMARY KEY, label TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $stmt = $pdo->prepare('INSERT INTO namespaces (namespace, is_active) VALUES (?, 1)');
        $stmt->execute([$resolvedNamespace]);

        $pdo->exec(
            'CREATE TABLE marketing_page_menu_items ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'page_id INTEGER, namespace TEXT, parent_id INTEGER, label TEXT, href TEXT, icon TEXT, layout TEXT, '
            . 'detail_title TEXT, detail_text TEXT, detail_subline TEXT, position INTEGER, is_external INTEGER, '
            . 'locale TEXT, is_active INTEGER, is_startpage INTEGER, updated_at TEXT)'
        );
        $menuInsert = $pdo->prepare(
            'INSERT INTO marketing_page_menu_items ('
            . 'page_id, namespace, parent_id, label, href, icon, layout, detail_title, detail_text, detail_subline, '
            . 'position, is_external, locale, is_active, is_startpage, updated_at'
            . ') VALUES (?, ?, NULL, ?, ?, NULL, ?, NULL, NULL, NULL, 0, 0, ?, 1, 0, NULL)'
        );
        $menuInsert->execute([$page->getId(), $contentNamespace, 'Home', '/', 'link', 'de']);

        $pdo->exec(
            'CREATE TABLE project_settings ('
            . 'namespace TEXT PRIMARY KEY, cookie_consent_enabled INTEGER, cookie_storage_key TEXT, cookie_banner_text TEXT, '
            . 'cookie_banner_text_de TEXT, cookie_banner_text_en TEXT, cookie_vendor_flags TEXT, privacy_url TEXT, '
            . 'privacy_url_de TEXT, privacy_url_en TEXT, marketing_wiki_themes TEXT, show_language_toggle INTEGER, '
            . 'show_theme_toggle INTEGER, show_contrast_toggle INTEGER, header_logo_mode TEXT, header_logo_path TEXT, '
            . 'header_logo_alt TEXT, header_logo_label TEXT, updated_at TEXT)'
        );
        $settingsInsert = $pdo->prepare(
            'INSERT INTO project_settings ('
            . 'namespace, cookie_consent_enabled, cookie_storage_key, cookie_banner_text, cookie_banner_text_de, '
            . 'cookie_banner_text_en, cookie_vendor_flags, privacy_url, privacy_url_de, privacy_url_en, marketing_wiki_themes, '
            . 'show_language_toggle, show_theme_toggle, show_contrast_toggle, header_logo_mode, header_logo_path, '
            . 'header_logo_alt, header_logo_label, updated_at'
            . ') VALUES (?, 0, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, "light", NULL, NULL, NULL, NULL)'
        );
        $settingsInsert->execute([$resolvedNamespace, 'testStorageKey']);

        $pdo->exec(
            'CREATE TABLE marketing_page_wiki_settings ('
            . 'page_id INTEGER PRIMARY KEY, is_active INTEGER, menu_label TEXT, menu_labels TEXT, updated_at TEXT)'
        );
        $pdo->exec(
            'CREATE TABLE marketing_page_wiki_articles ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER, locale TEXT, is_start_document INTEGER, '
            . 'sort_index INTEGER, title TEXT, content TEXT, is_published INTEGER, updated_at TEXT)'
        );

        $namespaceRepository = new NamespaceRepository($pdo);
        $designTokenService = new DesignTokenService($pdo, $configService);
        $namespaceService = new NamespaceService($namespaceRepository, new NamespaceValidator(), $designTokenService);

        $namespaceResolver = new NamespaceResolver(new NamespaceValidator());
        $namespaceServiceProperty = new ReflectionProperty(NamespaceResolver::class, 'namespaceService');
        $namespaceServiceProperty->setAccessible(true);
        $namespaceServiceProperty->setValue($namespaceResolver, $namespaceService);

        $effectsPolicy = $this->createMock(EffectsPolicyService::class);
        $effectsPolicy->method('getEffectsForNamespace')->willReturn([
            'effectsProfile' => 'quizrace.calm',
            'sliderProfile' => 'static',
        ]);

        $pageContentLoader = $this->createMock(PageContentLoader::class);
        $pageContentLoader->method('load')->willReturn($page->getContent());

        $namespaceAppearance = $this->createMock(NamespaceAppearanceService::class);
        $namespaceAppearance->method('load')->willReturnCallback(static fn (string $namespace): array => ['namespace' => $namespace]);

        $pageModules = $this->createMock(PageModuleService::class);
        $pageModules->method('getModulesByPosition')->willReturn([]);

        $seo = $this->createMock(PageSeoConfigService::class);
        $seo->method('load')->willReturn(null);

        $landingNews = $this->createMock(LandingNewsService::class);
        $landingNews->method('getPublishedForPage')->willReturn([]);

        $chatResponder = new class implements ChatResponderInterface {
            public function respond(array $messages, array $context): string
            {
                return json_encode(['items' => []]);
            }
        };
        $ragChatService = new RagChatService(null, null, $chatResponder, static fn (): array => []);
        $menuAiGenerator = new MarketingMenuAiGenerator($ragChatService, $chatResponder);
        $menuAiTranslator = new MarketingMenuAiTranslator($ragChatService, $chatResponder);
        $cmsMenu = new CmsPageMenuService($pdo, $pageService, $menuAiGenerator, $menuAiTranslator);

        $wikiSettings = new CmsPageWikiSettingsService($pdo);
        $wikiArticles = new CmsPageWikiArticleService($pdo);

        $projectSettings = new ProjectSettingsService($pdo);

        $provenExpert = $this->createMock(ProvenExpertRatingService::class);

        $turnstileConfig = $this->createMock(TurnstileConfig::class);
        $turnstileConfig->method('isEnabled')->willReturn(false);

        Database::setFactory(static fn (): PDO => $pdo);

        $controller = new CmsPageController(
            slug: 'styled',
            pages: $pageService,
            seo: $seo,
            turnstileConfig: $turnstileConfig,
            provenExpert: $provenExpert,
            landingNews: $landingNews,
            cmsMenu: $cmsMenu,
            wikiSettings: $wikiSettings,
            wikiArticles: $wikiArticles,
            contentLoader: $pageContentLoader,
            pageModules: $pageModules,
            namespaceAppearance: $namespaceAppearance,
            namespaceResolver: $namespaceResolver,
            projectSettings: $projectSettings,
            configService: $configService,
            effectsPolicy: $effectsPolicy
        );

        return $controller;
    }
}

