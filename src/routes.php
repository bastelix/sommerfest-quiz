<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controller\HomeController;
use App\Controller\FaqController;
use App\Controller\HelpController;
use App\Controller\DatenschutzController;
use App\Controller\ImpressumController;
use App\Controller\LizenzController;
use App\Controller\AdminController;
use App\Controller\AdminCatalogController;
use App\Controller\AdminMediaController;
use App\Controller\AdminLogsController;
use App\Controller\Admin\MarketingPageWikiController;
use App\Controller\LoginController;
use App\Controller\LogoutController;
use App\Controller\ConfigController;
use App\Controller\CatalogController;
use App\Application\Seo\PageSeoConfigService;
use App\Application\Middleware\HeadRequestMiddleware;
use App\Application\Middleware\RoleAuthMiddleware;
use App\Service\ConfigService;
use App\Service\ConfigValidator;
use App\Service\CatalogService;
use App\Service\ResultService;
use App\Service\TeamService;
use App\Service\TeamNameAiClient;
use App\Repository\TeamNameAiCacheRepository;
use App\Service\TeamNameService;
use App\Service\TeamNameWarmupDispatcher;
use App\Service\PhotoConsentService;
use App\Service\EventService;
use App\Service\SummaryPhotoService;
use App\Exception\PlayerNameConflictException;
use App\Service\PlayerService;
use App\Support\UsernameBlockedException;
use App\Support\UsernameGuard;
use App\Service\UserService;
use App\Service\TenantService;
use App\Service\NginxService;
use App\Service\SettingsService;
use App\Service\DomainStartPageService;
use App\Service\DomainContactTemplateService;
use App\Service\PageService;
use App\Service\TranslationService;
use App\Service\PasswordResetService;
use App\Service\PasswordPolicy;
use App\Service\PlayerContactOptInService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\EmailConfirmationService;
use App\Service\InvitationService;
use App\Service\AuditLogger;
use App\Service\QrCodeService;
use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\HttpChatResponder;
use App\Service\RagChat\OpenAiChatResponder;
use App\Service\RagChat\DomainIndexManager;
use App\Service\RagChat\DomainWikiSelectionService;
use App\Service\RagChat\RagChatService;
use App\Service\RagChat\RagChatServiceInterface;
use App\Service\SessionService;
use App\Service\StripeService;
use App\Service\VersionService;
use App\Service\MarketingNewsletterConfigService;
use App\Service\MarketingPageWikiArticleService;
use App\Service\MarketingDomainProvider;
use App\Service\CertificateProvisioningService;
use App\Service\UsernameBlocklistService;
use App\Infrastructure\Database;
use App\Infrastructure\MailProviderRepository;
use App\Infrastructure\Migrations\MigrationRuntime;
use App\Support\DomainNameHelper;
use App\Controller\Admin\ProfileController;
use App\Application\Middleware\LanguageMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\RateLimitMiddleware;
use App\Controller\ResultController;
use App\Controller\TeamController;
use App\Controller\TeamNameController;
use App\Controller\PasswordController;
use App\Controller\PasswordResetController;
use App\Controller\UserController;
use App\Controller\ImportController;
use App\Controller\ExportController;
use App\Controller\QrController;
use App\Controller\LogoController;
use App\Controller\QrLogoController;
use App\Controller\CatalogDesignController;
use App\Controller\SummaryController;
use App\Controller\RankingController;
use App\Controller\EvidenceController;
use App\Controller\EventController;
use App\Controller\EventListController;
use App\Controller\EventConfigController;
use App\Controller\DashboardController;
use App\Controller\SettingsController;
use App\Controller\Admin\PageController;
use App\Controller\Admin\LandingpageController;
use App\Controller\Admin\DomainChatKnowledgeController;
use App\Controller\Admin\DomainStartPageController;
use App\Controller\Admin\MailProviderController;
use App\Controller\Admin\UsernameBlocklistController;
use App\Controller\Admin\DomainContactTemplateController;
use App\Controller\Admin\MarketingNewsletterConfigController;
use App\Controller\Admin\LandingNewsController as AdminLandingNewsController;
use App\Controller\TenantController;
use App\Controller\Marketing\MarketingPageController;
use App\Controller\Marketing\MarketingPageWikiArticleController;
use App\Controller\Marketing\MarketingPageWikiListController;
use App\Controller\Marketing\ContactController;
use App\Controller\Marketing\LandingNewsController as MarketingLandingNewsController;
use App\Controller\Marketing\MarketingChatController;
use App\Controller\Marketing\NewsletterController;
use App\Controller\RegisterController;
use App\Controller\OnboardingController;
use App\Controller\OnboardingEmailController;
use App\Controller\OnboardingSessionController;
use App\Controller\CatalogSessionController;
use App\Controller\PlayerContactController;
use App\Controller\PlayerSessionController;
use App\Controller\StripeCheckoutController;
use App\Controller\StripeSessionController;
use App\Controller\StripeWebhookController;
use App\Controller\SubscriptionController;
use App\Controller\AdminSubscriptionCheckoutController;
use App\Controller\InvitationController;
use App\Controller\CatalogStickerController;
use App\Controller\EventImageController;
use App\Controller\GlobalMediaController;
use App\Service\ImageUploadService;
use App\Service\MediaLibraryService;
use App\Service\LandingMediaReferenceService;
use App\Service\LandingNewsService;
use Slim\Views\Twig;
use Slim\Psr7\Response as SlimResponse;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use App\Controller\BackupController;
use App\Domain\Roles;
use App\Domain\Plan;

use function App\runSyncProcess;

require_once __DIR__ . '/Controller/HomeController.php';
require_once __DIR__ . '/Controller/FaqController.php';
require_once __DIR__ . '/Controller/HelpController.php';
require_once __DIR__ . '/Controller/DatenschutzController.php';
require_once __DIR__ . '/Controller/ImpressumController.php';
require_once __DIR__ . '/Controller/LizenzController.php';
require_once __DIR__ . '/Controller/AdminController.php';
require_once __DIR__ . '/Controller/LoginController.php';
require_once __DIR__ . '/Controller/LogoutController.php';
require_once __DIR__ . '/Controller/ConfigController.php';
require_once __DIR__ . '/Controller/CatalogController.php';
require_once __DIR__ . '/Controller/ResultController.php';
require_once __DIR__ . '/Controller/TeamController.php';
require_once __DIR__ . '/Controller/PasswordController.php';
require_once __DIR__ . '/Controller/PasswordResetController.php';
require_once __DIR__ . '/Controller/AdminCatalogController.php';
require_once __DIR__ . '/Controller/AdminLogsController.php';
require_once __DIR__ . '/Controller/AdminMediaController.php';
require_once __DIR__ . '/Controller/Admin/PageController.php';
require_once __DIR__ . '/Controller/Admin/LandingpageController.php';
require_once __DIR__ . '/Controller/Admin/LandingNewsController.php';
require_once __DIR__ . '/Controller/Admin/DomainStartPageController.php';
require_once __DIR__ . '/Controller/Admin/MailProviderController.php';
require_once __DIR__ . '/Controller/Admin/MarketingPageWikiController.php';
require_once __DIR__ . '/Controller/QrController.php';
require_once __DIR__ . '/Controller/LogoController.php';
require_once __DIR__ . '/Controller/CatalogDesignController.php';
require_once __DIR__ . '/Controller/SummaryController.php';
require_once __DIR__ . '/Controller/RankingController.php';
require_once __DIR__ . '/Controller/EvidenceController.php';
require_once __DIR__ . '/Controller/ExportController.php';
require_once __DIR__ . '/Controller/EventController.php';
require_once __DIR__ . '/Controller/EventListController.php';
require_once __DIR__ . '/Controller/EventConfigController.php';
require_once __DIR__ . '/Controller/DashboardController.php';
require_once __DIR__ . '/Controller/SettingsController.php';
require_once __DIR__ . '/Controller/BackupController.php';
require_once __DIR__ . '/Controller/UserController.php';
require_once __DIR__ . '/Controller/TenantController.php';
require_once __DIR__ . '/Controller/Marketing/MarketingPageController.php';
require_once __DIR__ . '/Controller/Marketing/LandingController.php';
require_once __DIR__ . '/Controller/Marketing/CalserverController.php';
require_once __DIR__ . '/Controller/Marketing/MarketingPageWikiListController.php';
require_once __DIR__ . '/Controller/Marketing/MarketingPageWikiArticleController.php';
require_once __DIR__ . '/Controller/Marketing/MarketingChatController.php';
require_once __DIR__ . '/Controller/Marketing/ContactController.php';
require_once __DIR__ . '/Controller/Marketing/NewsletterController.php';
require_once __DIR__ . '/Controller/Marketing/LandingNewsController.php';
require_once __DIR__ . '/Controller/RegisterController.php';
require_once __DIR__ . '/Controller/OnboardingController.php';
require_once __DIR__ . '/Controller/OnboardingEmailController.php';
require_once __DIR__ . '/Controller/OnboardingSessionController.php';
require_once __DIR__ . '/Controller/CatalogSessionController.php';
require_once __DIR__ . '/Controller/PlayerSessionController.php';
require_once __DIR__ . '/Controller/StripeCheckoutController.php';
require_once __DIR__ . '/Controller/StripeSessionController.php';
require_once __DIR__ . '/Controller/StripeWebhookController.php';
require_once __DIR__ . '/Controller/SubscriptionController.php';
require_once __DIR__ . '/Controller/AdminSubscriptionCheckoutController.php';
require_once __DIR__ . '/Controller/InvitationController.php';
require_once __DIR__ . '/Controller/CatalogStickerController.php';
require_once __DIR__ . '/Controller/EventImageController.php';
require_once __DIR__ . '/Controller/GlobalMediaController.php';

use App\Infrastructure\Migrations\Migrator;
use Psr\Http\Server\RequestHandlerInterface;

return function (\Slim\App $app, TranslationService $translator) {
    $app->addBodyParsingMiddleware();

    $resolveMarketingAccess = static function (Request $request): array {
        $domainType = $request->getAttribute('domainType');
        if (!in_array($domainType, ['main', 'marketing'], true)) {
            $host = strtolower($request->getUri()->getHost());
            $mainDomain = strtolower((string) getenv('MAIN_DOMAIN'));
            $marketingDomains = getenv('MARKETING_DOMAINS') ?: '';

            $computed = 'tenant';
            if ($mainDomain === '' || $host === $mainDomain) {
                $computed = 'main';
            } else {
                $marketingList = array_filter(preg_split('/[\s,]+/', strtolower($marketingDomains)) ?: []);
                $marketingList = array_map(
                    static fn (string $domain): string => DomainNameHelper::normalize($domain, stripAdmin: false),
                    $marketingList
                );
                $normalizedHost = DomainNameHelper::normalize($host, stripAdmin: false);
                if (in_array($normalizedHost, $marketingList, true)) {
                    $computed = 'marketing';
                }
            }

            $request = $request->withAttribute('domainType', $computed);
            $domainType = $computed;
        }

        return [$request, in_array($domainType, ['main', 'marketing'], true)];
    };
    $app->add(function (Request $request, RequestHandlerInterface $handler) use ($translator) {
        if ($request->getUri()->getPath() === '/healthz') {
            return $handler->handle($request);
        }

        $base = Database::connectFromEnv();
        MigrationRuntime::ensureUpToDate($base, __DIR__ . '/../migrations', 'base');

        $host = $request->getUri()->getHost();
        $domainType = $request->getAttribute('domainType');
        $sub = $domainType === 'main' ? 'main' : explode('.', $host)[0];
        $stmt = $base->prepare('SELECT subdomain FROM tenants WHERE subdomain = ?');
        $stmt->execute([$sub]);
        $schema = $stmt->fetchColumn();
        $schema = $schema === false || $schema === 'main' ? 'public' : (string) $schema;

        $pdo = Database::connectWithSchema($schema);
        MigrationRuntime::ensureUpToDate($pdo, __DIR__ . '/../migrations', 'schema:' . $schema);

        $nginxService = new NginxService();
        $tenantService = new TenantService($base, null, $nginxService);

        $configService = new ConfigService($pdo);
        $eventService = new EventService($pdo, $configService, $tenantService, $sub);
        $params = $request->getQueryParams();
        $evParam = (string)($params['event'] ?? '');
        $eventUid = $evParam !== '' && !preg_match('/^[0-9a-fA-F]{32}$/', $evParam)
            ? $eventService->uidBySlug($evParam) ?? ''
            : $evParam;
        if ($eventUid === '') {
            $eventUid = (string) ($_SESSION['event_uid'] ?? '');
        }
        if ($eventUid !== '') {
            $_SESSION['event_uid'] = $eventUid;
        }
        $catalogService = new CatalogService($pdo, $configService, $tenantService, $sub, $eventUid);
        $resultService = new ResultService($pdo);
        $teamService = new TeamService($pdo, $configService, $tenantService, $sub);

        $teamNameAiClient = null;
        $teamNameAiEnabled = true;
        $teamNameAiModelEnv = getenv('RAG_CHAT_SERVICE_MODEL');

        try {
            $endpointEnv = getenv('RAG_CHAT_SERVICE_URL');
            $endpoint = $endpointEnv !== false ? trim((string) $endpointEnv) : '';
            if ($endpoint === '') {
                throw new \RuntimeException('Chat service URL is not configured.');
            }

            $tokenEnv = getenv('RAG_CHAT_SERVICE_TOKEN');
            $token = $tokenEnv !== false ? trim((string) $tokenEnv) : null;
            $token = $token === '' ? null : $token;

            $driverEnv = getenv('RAG_CHAT_SERVICE_DRIVER');
            $forceOpenAiEnv = getenv('RAG_CHAT_SERVICE_FORCE_OPENAI');
            $modelEnv = $teamNameAiModelEnv !== false ? trim((string) $teamNameAiModelEnv) : null;

            $isTruthy = static function (?string $value): bool {
                if ($value === null) {
                    return false;
                }

                $normalised = strtolower(trim($value));

                return $normalised !== '' && in_array($normalised, ['1', 'true', 'yes', 'on'], true);
            };

            $shouldUseOpenAi = false;
            if ($driverEnv !== false) {
                $normalisedDriver = strtolower(trim((string) $driverEnv));
                if ($normalisedDriver === 'openai') {
                    $shouldUseOpenAi = true;
                } elseif ($normalisedDriver !== '') {
                    $shouldUseOpenAi = false;
                }
            }

            if (!$shouldUseOpenAi) {
                $parts = parse_url($endpoint);
                if (is_array($parts)) {
                    $host = $parts['host'] ?? null;
                    if (is_string($host) && $host === 'api.openai.com') {
                        $shouldUseOpenAi = true;
                    }

                    if (!$shouldUseOpenAi) {
                        $pathValue = $parts['path'] ?? null;
                        $path = is_string($pathValue) ? rtrim($pathValue, '/') : '';
                        if ($path === '/v1' || $path === '/v1/models' || str_ends_with($path, '/v1/chat/completions')) {
                            $shouldUseOpenAi = true;
                        }
                    }
                }
            }

            if (!$shouldUseOpenAi && $isTruthy($forceOpenAiEnv !== false ? (string) $forceOpenAiEnv : null)) {
                $shouldUseOpenAi = true;
            }

            if ($shouldUseOpenAi) {
                $normalizeOpenAiEndpoint = static function (string $value): string {
                    $trimmed = trim($value);
                    if ($trimmed === '') {
                        return $value;
                    }

                    $parts = parse_url($trimmed);
                    if ($parts === false) {
                        return $value;
                    }

                    $scheme = $parts['scheme'] ?? null;
                    $host = $parts['host'] ?? null;
                    if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
                        return $value;
                    }

                    $pathValue = $parts['path'] ?? null;
                    $path = is_string($pathValue) ? $pathValue : '';
                    $normalisePath = static function (string $path): string {
                        $normalised = rtrim($path, '/');
                        if ($normalised === '' || $normalised === '/v1' || $normalised === '/v1/models') {
                            return '/v1/chat/completions';
                        }

                        if (str_ends_with($normalised, '/v1/chat/completions')) {
                            return $normalised;
                        }

                        return $path === '' ? '/v1/chat/completions' : $path;
                    };

                    $rebuilt = $scheme . '://';

                    $userInfo = '';
                    $user = $parts['user'] ?? null;
                    if (is_string($user) && $user !== '') {
                        $userInfo = $user;
                        $pass = $parts['pass'] ?? null;
                        if (is_string($pass)) {
                            $userInfo .= ':' . $pass;
                        }
                        $userInfo .= '@';
                    }

                    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                    $rebuilt .= $userInfo . $host . $port . $normalisePath($path);

                    $query = $parts['query'] ?? null;
                    if (is_string($query) && $query !== '') {
                        $rebuilt .= '?' . $query;
                    }

                    $fragment = $parts['fragment'] ?? null;
                    if (is_string($fragment) && $fragment !== '') {
                        $rebuilt .= '#' . $fragment;
                    }

                    return $rebuilt;
                };

                $options = [];
                $temperatureEnv = getenv('RAG_CHAT_SERVICE_TEMPERATURE');
                $temperature = $temperatureEnv !== false ? trim((string) $temperatureEnv) : '';
                if ($temperature !== '' && is_numeric($temperature)) {
                    $options['temperature'] = (float) $temperature;
                }

                $topPEnv = getenv('RAG_CHAT_SERVICE_TOP_P');
                $topP = $topPEnv !== false ? trim((string) $topPEnv) : '';
                if ($topP !== '' && is_numeric($topP)) {
                    $options['top_p'] = (float) $topP;
                }

                $presenceEnv = getenv('RAG_CHAT_SERVICE_PRESENCE_PENALTY');
                $presence = $presenceEnv !== false ? trim((string) $presenceEnv) : '';
                if ($presence !== '' && is_numeric($presence)) {
                    $options['presence_penalty'] = (float) $presence;
                }

                $frequencyEnv = getenv('RAG_CHAT_SERVICE_FREQUENCY_PENALTY');
                $frequency = $frequencyEnv !== false ? trim((string) $frequencyEnv) : '';
                if ($frequency !== '' && is_numeric($frequency)) {
                    $options['frequency_penalty'] = (float) $frequency;
                }

                $maxTokensEnv = getenv('RAG_CHAT_SERVICE_MAX_TOKENS');
                $maxTokens = $maxTokensEnv !== false ? trim((string) $maxTokensEnv) : '';
                if ($maxTokens !== '' && is_numeric($maxTokens)) {
                    $options['max_tokens'] = (int) $maxTokens;
                }

                $teamNameAiResponder = new OpenAiChatResponder(
                    $normalizeOpenAiEndpoint($endpoint),
                    null,
                    $token,
                    null,
                    $modelEnv,
                    $options === [] ? null : $options
                );
            } else {
                $teamNameAiResponder = new HttpChatResponder($endpoint, null, $token);
            }

            $teamNameAiClient = new TeamNameAiClient(
                $teamNameAiResponder,
                $modelEnv
            );
        } catch (\RuntimeException $exception) {
            $teamNameAiClient = null;
            $teamNameAiEnabled = false;
        }

        if ($teamNameAiClient === null) {
            $teamNameAiEnabled = false;
        }

        $teamNameAiCacheRepository = new TeamNameAiCacheRepository($pdo);
        $teamNameWarmupDispatcher = new TeamNameWarmupDispatcher($schema);

        $teamNameService = new TeamNameService(
            $pdo,
            __DIR__ . '/../resources/team-names/lexicon.json',
            $teamNameAiCacheRepository,
            600,
            $teamNameAiClient,
            $teamNameAiEnabled,
            null,
            $teamNameWarmupDispatcher
        );
        $configService->setTeamNameService($teamNameService);
        $configService->setTeamNameWarmupDispatcher($teamNameWarmupDispatcher);
        $consentService = new PhotoConsentService($pdo, $configService);
        $summaryService = new SummaryPhotoService($pdo, $configService);
        $plan = $tenantService->getPlanBySubdomain($sub);
        $userService = new \App\Service\UserService($pdo);
        $settingsService = new \App\Service\SettingsService($pdo);
        $domainStartPageService = new DomainStartPageService($pdo);
        $certificateProvisioner = new CertificateProvisioningService($domainStartPageService);
        $domainContactTemplateService = new DomainContactTemplateService($pdo, $domainStartPageService);
        $marketingNewsletterConfigService = new MarketingNewsletterConfigService($pdo);
        $marketingDomainProvider = new MarketingDomainProvider(
            static function () use ($schema): \PDO {
                return Database::connectWithSchema($schema);
            }
        );
        DomainNameHelper::setMarketingDomainProvider($marketingDomainProvider);
        $passwordResetService = new PasswordResetService(
            $pdo,
            3600,
            getenv('PASSWORD_RESET_SECRET') ?: ''
        );
        $passwordPolicy = new PasswordPolicy();
        $emailConfirmService = new EmailConfirmationService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $sessionService = new SessionService($pdo);
        $usernameGuard = UsernameGuard::fromConfigFile(null, $pdo);
        $playerService = new PlayerService($pdo, $usernameGuard);
        $playerContactOptInService = new PlayerContactOptInService($pdo, $playerService);
        $imageUploadService = new ImageUploadService();
        $mediaLibraryService = new MediaLibraryService($configService, $imageUploadService);
        $pageService = new PageService($pdo);
        $landingReferenceService = new LandingMediaReferenceService(
            $pageService,
            new PageSeoConfigService($pdo),
            $configService,
            new LandingNewsService($pdo)
        );
        $domainDocumentStorage = new DomainDocumentStorage();
        $wikiArticleService = new MarketingPageWikiArticleService($pdo);
        $domainWikiSelectionService = new DomainWikiSelectionService($pdo);
        $domainIndexManager = new DomainIndexManager(
            $domainDocumentStorage,
            null,
            'python3',
            $domainWikiSelectionService,
            $wikiArticleService
        );
        $mailProviderRepository = null;
        try {
            $mailProviderRepository = new MailProviderRepository($pdo);
        } catch (\RuntimeException $exception) {
            $mailProviderRepository = null;
        }
        $mailProviderManager = new MailProviderManager($settingsService, [], $mailProviderRepository);

        $request = $request
            ->withAttribute('plan', $plan)
            ->withAttribute('configService', $configService)
            ->withAttribute(
                'configController',
                new ConfigController(
                    $configService,
                    new ConfigValidator(),
                    $eventService
                )
            )
            ->withAttribute('catalogController', new CatalogController($catalogService))
            ->withAttribute('adminCatalogController', new AdminCatalogController($catalogService))
            ->withAttribute('resultController', new ResultController(
                $resultService,
                $configService,
                $teamService,
                $catalogService,
                __DIR__ . '/../data/photos',
                $eventService
            ))
            ->withAttribute('teamController', new TeamController($teamService, $configService, $resultService))
            ->withAttribute('teamNameController', new TeamNameController($teamNameService, $configService))
            ->withAttribute('eventController', new EventController($eventService))
            ->withAttribute(
                'eventConfigController',
                new EventConfigController($eventService, $configService, $imageUploadService)
            )
            ->withAttribute('dashboardController', new DashboardController($configService, $eventService))
            ->withAttribute(
                'tenantController',
                new TenantController(
                    $tenantService,
                    filter_var(getenv('DISPLAY_ERROR_DETAILS'), FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->withAttribute('auditLogger', $auditLogger)
            ->withAttribute(
                'passwordController',
                new PasswordController(
                    $userService,
                    $passwordPolicy,
                    $auditLogger,
                    $sessionService
                )
            )
            ->withAttribute(
                'passwordResetController',
                new PasswordResetController($userService, $passwordResetService, $passwordPolicy, $sessionService)
            )
            ->withAttribute('userController', new UserController($userService))
            ->withAttribute('settingsController', new SettingsController($settingsService))
            ->withAttribute(
                'domainStartPageController',
                new DomainStartPageController(
                    $domainStartPageService,
                    $certificateProvisioner,
                    $settingsService,
                    $pageService,
                    $marketingDomainProvider
                )
            )
            ->withAttribute(
                'domainContactTemplateController',
                new DomainContactTemplateController($domainContactTemplateService, $domainStartPageService)
            )
            ->withAttribute(
                'marketingNewsletterConfigController',
                new MarketingNewsletterConfigController($marketingNewsletterConfigService)
            )
            ->withAttribute(
                'domainChatController',
                new DomainChatKnowledgeController(
                    $domainDocumentStorage,
                    $domainIndexManager,
                    $domainWikiSelectionService,
                    $wikiArticleService,
                    $pageService
                )
            )
            ->withAttribute('qrController', new QrController(
                $configService,
                $teamService,
                $eventService,
                $catalogService,
                new QrCodeService(),
                $resultService
            ))
            ->withAttribute('onboardingEmailController', new OnboardingEmailController($emailConfirmService))
            ->withAttribute('catalogDesignController', new CatalogDesignController($catalogService))
            ->withAttribute('mediaLibraryService', $mediaLibraryService)
            ->withAttribute('landingMediaReferenceService', $landingReferenceService)
            ->withAttribute('adminMediaController', new AdminMediaController(
                $mediaLibraryService,
                $landingReferenceService
            ))
            ->withAttribute('logoController', new LogoController($configService, $imageUploadService))
            ->withAttribute('qrLogoController', new QrLogoController($configService, $imageUploadService))
            ->withAttribute('summaryController', new SummaryController($configService, $eventService))
            ->withAttribute('rankingController', new RankingController($configService, $eventService))
            ->withAttribute('playerContactController', new PlayerContactController($playerContactOptInService, $eventService))
            ->withAttribute('mailProviderManager', $mailProviderManager)
            ->withAttribute('mailProviderController', new MailProviderController(
                $mailProviderRepository,
                $settingsService,
                $mailProviderManager,
                $domainStartPageService
            ))
            ->withAttribute('usernameBlocklistController', new UsernameBlocklistController(
                new UsernameBlocklistService($pdo),
                $configService,
                $translator
            ))
            ->withAttribute('catalogStickerController', new CatalogStickerController(
                $configService,
                $eventService,
                $catalogService,
                new QrCodeService(),
                $imageUploadService
            ))
            ->withAttribute('eventImageController', new EventImageController($configService))
            ->withAttribute('globalMediaController', new GlobalMediaController($configService))
            ->withAttribute('importController', $importController = new ImportController(
                $catalogService,
                $configService,
                $resultService,
                $teamService,
                $consentService,
                $summaryService,
                $eventService,
                __DIR__ . '/../data',
                __DIR__ . '/../backup'
            ))
            ->withAttribute('exportController', new ExportController(
                $configService,
                $catalogService,
                $resultService,
                $teamService,
                $consentService,
                $summaryService,
                $eventService,
                __DIR__ . '/../data',
                __DIR__ . '/../backup'
            ))
            ->withAttribute('backupController', new BackupController(__DIR__ . '/../backup', $importController))
            ->withAttribute('evidenceController', new EvidenceController(
                $configService,
                $resultService,
                $consentService,
                $summaryService,
                new NullLogger(),
                $imageUploadService
            ))
            ->withAttribute('pdo', $pdo)
            ->withAttribute('translator', $translator)
            ->withAttribute('lang', $translator->getLocale())
            ->withAttribute('playerService', $playerService);

        return $handler->handle($request);
    });

    $app->add(static function (Request $request, RequestHandlerInterface $handler): Response {
        if ($request->getMethod() === 'OPTIONS') {
            return (new SlimResponse())->withStatus(204);
        }

        return $handler->handle($request);
    });
    $app->add(new HeadRequestMiddleware());
    $app->add(new LanguageMiddleware($translator));

    $app->map(['GET', 'HEAD'], '/healthz', function (Request $request, Response $response) {
        $version = getenv('APP_VERSION');
        if ($version === false || $version === '') {
            $version = (new VersionService())->getCurrentVersion();
        }
        $payload = [
            'status'  => 'ok',
            'app'     => 'quizrace',
            'version' => $version,
            'time'    => gmdate('c'),
        ];
        $payloadJson = json_encode($payload);

        if (strtoupper($request->getMethod()) !== 'HEAD') {
            $response->getBody()->write($payloadJson);
        }

        $response = $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*');

        return $response;
    });

    $app->get('/', HomeController::class)->add(new CsrfMiddleware());
    $app->get('/favicon.ico', function (Request $request, Response $response) {
        $iconPath = __DIR__ . '/../public/favicon.svg';
        if (file_exists($iconPath)) {
            $response->getBody()->write(file_get_contents($iconPath));
            return $response->withHeader('Content-Type', 'image/svg+xml');
        }
        return $response->withStatus(404);
    });
    $app->get('/faq', FaqController::class);
    $app->get('/help', HelpController::class);
    $app->get('/events', EventListController::class);
    $app->get('/datenschutz', DatenschutzController::class);
    $app->get('/impressum', ImpressumController::class);
    $app->get('/lizenz', LizenzController::class);
    $app->get('/profile', function (Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $configService = new ConfigService($pdo);
        $config = $configService->getConfig();
        $role = $_SESSION['user']['role'] ?? null;
        if ($role !== 'admin') {
            $config = ConfigService::removePuzzleInfo($config);
        }
        $params = $request->getQueryParams();
        $return = (string)($params['return'] ?? '');
        $eventUid = (string)($config['event_uid'] ?? '');
        if ($eventUid === '') {
            $eventUid = $configService->getActiveEventUid();
            if ($eventUid !== '') {
                $config['event_uid'] = $eventUid;
            }
        }
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        return $view->render($response, 'profile.twig', [
            'config'     => $config,
            'return'     => $return,
            'eventUid'   => $eventUid,
            'csrf_token' => $csrf,
        ]);
    });
    $app->get('/landing', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('landing');
        return $controller($request, $response);
    });
    $app->get('/landing/news', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingLandingNewsController();
        return $controller->index($request, $response, ['landingSlug' => 'landing']);
    });
    $app->get('/landing/news/{newsSlug:[a-z0-9-]+}', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingLandingNewsController();
        $args['landingSlug'] = 'landing';
        return $controller->show($request, $response, $args);
    });
    $app->get('/{landingSlug:[a-z0-9-]+}/news', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingLandingNewsController();
        return $controller->index($request, $response, $args);
    });
    $app->get('/{landingSlug:[a-z0-9-]+}/news/{newsSlug:[a-z0-9-]+}', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingLandingNewsController();
        return $controller->show($request, $response, $args);
    });
    $app->get('/future-is-green', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new \App\Controller\Marketing\FutureIsGreenController();
        return $controller($request, $response);
    });
    $app->get('/calhelp', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('calhelp');
        return $controller($request, $response);
    });

    $app->get('/calserver', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('calserver');
        return $controller($request, $response);
    });
    $app->get('/labor', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('labor');
        return $controller($request, $response);
    });
    $app->get('/fluke-metcal', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('fluke-metcal');
        return $controller($request, $response);
    });
    $app->get('/calserver/barrierefreiheit', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('calserver-accessibility');
        return $controller($request, $response);
    });
    $app->get('/calserver/accessibility', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('calserver-accessibility');
        return $controller($request, $response);
    });
    $app->get('/calserver-maintenance', function (Request $request, Response $response) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingPageController('calserver-maintenance');
        return $controller($request, $response);
    });
    $app->get('/pages/{slug:[a-z0-9-]+}/wiki', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }

        $controller = new MarketingPageWikiListController();

        return $controller($request, $response, $args);
    });
    $app->get('/pages/{slug:[a-z0-9-]+}/wiki/{articleSlug:[a-z0-9-]+}', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }

        $controller = new MarketingPageWikiArticleController();

        return $controller($request, $response, $args);
    });
    $app->get('/m/{landingSlug:[a-z0-9-]+}/news', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingLandingNewsController();
        return $controller->index($request, $response, $args);
    });
    $app->get('/m/{slug:[a-z0-9-]+}/wiki', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }

        $controller = new MarketingPageWikiListController();

        return $controller($request, $response, $args);
    });
    $app->get('/m/{slug:[a-z0-9-]+}/wiki/{articleSlug:[a-z0-9-]+}', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }

        $controller = new MarketingPageWikiArticleController();

        return $controller($request, $response, $args);
    });
    $app->get('/m/{landingSlug:[a-z0-9-]+}/news/{newsSlug:[a-z0-9-]+}', function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
        [$request, $allowed] = $resolveMarketingAccess($request);
        if (!$allowed) {
            return $response->withStatus(404);
        }
        $controller = new MarketingLandingNewsController();
        return $controller->show($request, $response, $args);
    });
    $app->get(
        '/m/{slug:[a-z0-9-]+}',
        function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
            [$request, $allowed] = $resolveMarketingAccess($request);
            if (!$allowed) {
                return $response->withStatus(404);
            }
            $controller = new MarketingPageController();
            return $controller($request, $response, $args);
        }
    );
    $app->post('/landing/contact', ContactController::class)
        ->add(new RateLimitMiddleware(3, 3600))
        ->add(new CsrfMiddleware());
    $app->post('/future-is-green/contact', ContactController::class)
        ->add(new RateLimitMiddleware(3, 3600))
        ->add(new CsrfMiddleware());
    $app->post('/calserver/contact', ContactController::class)
        ->add(new RateLimitMiddleware(3, 3600))
        ->add(new CsrfMiddleware());
    $app->get('/newsletter/confirm', function (Request $request, Response $response): Response {
        $controller = new NewsletterController();

        return $controller->confirm($request, $response);
    });
    $app->post('/newsletter/unsubscribe', function (Request $request, Response $response): Response {
        $controller = new NewsletterController();

        return $controller->unsubscribe($request, $response);
    })
        ->add(new RateLimitMiddleware(5, 3600))
        ->add(new CsrfMiddleware());
    $createChatHandler = static function (?string $slug = null) {
        return static function (Request $request, Response $response) use ($slug): Response {
            $service = $request->getAttribute('ragChatService');
            if (!$service instanceof RagChatServiceInterface) {
                $service = null;
            }

            $controller = new MarketingChatController($slug, $service);

            return $controller($request, $response);
        };
    };

    $app->post('/calserver/chat', $createChatHandler('calserver'))
        ->add(new RateLimitMiddleware(10, 60))
        ->add(new CsrfMiddleware());
    $app->post('/calhelp/chat', $createChatHandler('calhelp'))
        ->add(new RateLimitMiddleware(10, 60))
        ->add(new CsrfMiddleware());

    $app->post(
        '/m/{marketingSlug:[a-z0-9-]+}/chat',
        function (Request $request, Response $response, array $args) use ($createChatHandler, $resolveMarketingAccess): Response {
            [$request, $allowed] = $resolveMarketingAccess($request);
            if (!$allowed) {
                return $response->withStatus(404);
            }

            $slug = isset($args['marketingSlug']) ? (string) $args['marketingSlug'] : null;

            return $createChatHandler($slug)($request, $response);
        }
    )
        ->add(new RateLimitMiddleware(10, 60))
        ->add(new CsrfMiddleware());
    $app->get('/onboarding', OnboardingController::class);
    $app->post('/onboarding/email', function (Request $request, Response $response) {
        return $request->getAttribute('onboardingEmailController')->request($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->get('/onboarding/email/confirm', function (Request $request, Response $response) {
        return $request->getAttribute('onboardingEmailController')->confirm($request, $response);
    });
    $app->get('/onboarding/email/status', function (Request $request, Response $response) {
        return $request->getAttribute('onboardingEmailController')->status($request, $response);
    });
    $app->get('/onboarding/session', function (Request $request, Response $response) {
        $controller = new OnboardingSessionController();
        return $controller->get($request, $response);
    });
    $app->post('/onboarding/session', function (Request $request, Response $response) {
        $controller = new OnboardingSessionController();
        return $controller->store($request, $response);
    })->add(new CsrfMiddleware());
    $app->delete('/onboarding/session', function (Request $request, Response $response) {
        $controller = new OnboardingSessionController();
        return $controller->clear($request, $response);
    })->add(new CsrfMiddleware());
    $app->post('/session/catalog', CatalogSessionController::class)->add(new CsrfMiddleware());
    $app->map(['POST', 'DELETE'], '/session/player', PlayerSessionController::class)
        ->add(new CsrfMiddleware());
    $app->get('/onboarding/tenants/{subdomain}', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $sub = strtolower((string) ($args['subdomain'] ?? ''));
        if ($sub === '' || !preg_match('/^[a-z0-9-]{3,63}$/', $sub)) {
            return $response->withStatus(400);
        }
        $args['subdomain'] = $sub;
        return $request->getAttribute('tenantController')->exists($request, $response, $args);
    })->add(new RateLimitMiddleware(10, 60));
    $app->post('/onboarding/checkout', StripeCheckoutController::class);
    $app->get('/onboarding/checkout/{id}', StripeSessionController::class);
    $app->post('/onboarding/checkout/{id}/cancel', [SubscriptionController::class, 'cancelOnboardingCheckout']);
    $app->post('/stripe/webhook', StripeWebhookController::class);
    $app->get('/login', [LoginController::class, 'show']);
    $app->post('/login', [LoginController::class, 'login'])
        ->add(new RateLimitMiddleware(3, 3600))
        ->add(new CsrfMiddleware());
    $app->get('/register', [RegisterController::class, 'show']);
    $app->post('/register', [RegisterController::class, 'register']);
    $app->get('/password/reset/request', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        return $view->render($response, 'password_request.twig', ['csrf_token' => $csrf]);
    });
    $app->get('/password/reset', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        return $view->render($response, 'password_confirm.twig', [
            'token'      => $token,
            'csrf_token' => $csrf,
            'action'     => '/password/reset/confirm',
        ]);
    });

    $app->get('/password/set', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        $next = (string) ($request->getQueryParams()['next'] ?? '');
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        return $view->render($response, 'password_confirm.twig', [
            'token'      => $token,
            'csrf_token' => $csrf,
            'action'     => '/password/set',
            'next'       => $next,
        ]);
    });
    $app->get('/logout', LogoutController::class);
    $app->get('/admin', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/dashboard')->withStatus(302);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/dashboard', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/events', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/event/settings', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/event/dashboard', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/konfig', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/questions', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/teams', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/summary', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/results', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/statistics', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/logs', AdminLogsController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/media', AdminController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/admin/media/files', function (Request $request, Response $response): Response {
        $controller = $request->getAttribute('adminMediaController');
        if (!$controller instanceof AdminMediaController) {
            $service = $request->getAttribute('mediaLibraryService');
            $config = $request->getAttribute('configService');
            if ($service instanceof MediaLibraryService && $config instanceof ConfigService) {
                $landing = $request->getAttribute('landingMediaReferenceService');
                if (!$landing instanceof LandingMediaReferenceService) {
                    $pdo = Database::connectFromEnv();
                    $landing = new LandingMediaReferenceService(
                        new PageService($pdo),
                        new PageSeoConfigService($pdo),
                        $config,
                        new LandingNewsService($pdo)
                    );
                }
                $controller = new AdminMediaController($service, $landing);
            } else {
                return $response->withStatus(500);
            }
        }
        return $controller->list($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR))->add(new CsrfMiddleware());
    $app->post('/admin/media/upload', function (Request $request, Response $response): Response {
        $controller = $request->getAttribute('adminMediaController');
        if (!$controller instanceof AdminMediaController) {
            $service = $request->getAttribute('mediaLibraryService');
            $config = $request->getAttribute('configService');
            if ($service instanceof MediaLibraryService && $config instanceof ConfigService) {
                $landing = $request->getAttribute('landingMediaReferenceService');
                if (!$landing instanceof LandingMediaReferenceService) {
                    $pdo = Database::connectFromEnv();
                    $landing = new LandingMediaReferenceService(
                        new PageService($pdo),
                        new PageSeoConfigService($pdo),
                        $config
                    );
                }
                $controller = new AdminMediaController($service, $landing);
            } else {
                return $response->withStatus(500);
            }
        }
        return $controller->upload($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR))->add(new CsrfMiddleware());
    $app->post('/admin/media/replace', function (Request $request, Response $response): Response {
        $controller = $request->getAttribute('adminMediaController');
        if (!$controller instanceof AdminMediaController) {
            $service = $request->getAttribute('mediaLibraryService');
            $config = $request->getAttribute('configService');
            if ($service instanceof MediaLibraryService && $config instanceof ConfigService) {
                $landing = $request->getAttribute('landingMediaReferenceService');
                if (!$landing instanceof LandingMediaReferenceService) {
                    $pdo = Database::connectFromEnv();
                    $landing = new LandingMediaReferenceService(
                        new PageService($pdo),
                        new PageSeoConfigService($pdo),
                        $config
                    );
                }
                $controller = new AdminMediaController($service, $landing);
            } else {
                return $response->withStatus(500);
            }
        }
        return $controller->replace($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR))->add(new CsrfMiddleware());
    $app->post('/admin/media/convert', function (Request $request, Response $response): Response {
        $controller = $request->getAttribute('adminMediaController');
        if (!$controller instanceof AdminMediaController) {
            $service = $request->getAttribute('mediaLibraryService');
            $config = $request->getAttribute('configService');
            if ($service instanceof MediaLibraryService && $config instanceof ConfigService) {
                $landing = $request->getAttribute('landingMediaReferenceService');
                if (!$landing instanceof LandingMediaReferenceService) {
                    $pdo = Database::connectFromEnv();
                    $landing = new LandingMediaReferenceService(
                        new PageService($pdo),
                        new PageSeoConfigService($pdo),
                        $config
                    );
                }
                $controller = new AdminMediaController($service, $landing);
            } else {
                return $response->withStatus(500);
            }
        }
        return $controller->convert($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR))->add(new CsrfMiddleware());
    $app->post('/admin/media/rename', function (Request $request, Response $response): Response {
        $controller = $request->getAttribute('adminMediaController');
        if (!$controller instanceof AdminMediaController) {
            $service = $request->getAttribute('mediaLibraryService');
            $config = $request->getAttribute('configService');
            if ($service instanceof MediaLibraryService && $config instanceof ConfigService) {
                $landing = $request->getAttribute('landingMediaReferenceService');
                if (!$landing instanceof LandingMediaReferenceService) {
                    $pdo = Database::connectFromEnv();
                    $landing = new LandingMediaReferenceService(
                        new PageService($pdo),
                        new PageSeoConfigService($pdo),
                        $config
                    );
                }
                $controller = new AdminMediaController($service, $landing);
            } else {
                return $response->withStatus(500);
            }
        }
        return $controller->rename($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR))->add(new CsrfMiddleware());
    $app->post('/admin/media/delete', function (Request $request, Response $response): Response {
        $controller = $request->getAttribute('adminMediaController');
        if (!$controller instanceof AdminMediaController) {
            $service = $request->getAttribute('mediaLibraryService');
            $config = $request->getAttribute('configService');
            if ($service instanceof MediaLibraryService && $config instanceof ConfigService) {
                $landing = $request->getAttribute('landingMediaReferenceService');
                if (!$landing instanceof LandingMediaReferenceService) {
                    $pdo = Database::connectFromEnv();
                    $landing = new LandingMediaReferenceService(
                        new PageService($pdo),
                        new PageSeoConfigService($pdo),
                        $config
                    );
                }
                $controller = new AdminMediaController($service, $landing);
            } else {
                return $response->withStatus(500);
            }
        }
        return $controller->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR))->add(new CsrfMiddleware());
    $app->get('/admin/dashboard.json', function (Request $request, Response $response) {
        $month = (string)($request->getQueryParams()['month'] ?? (new DateTimeImmutable('now'))->format('Y-m'));
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01')
            ?: new DateTimeImmutable('first day of this month');
        $end = $start->modify('first day of next month');
        $stmt = $pdo->prepare(
            'SELECT uid,name,start_date,end_date,published '
            . 'FROM events '
            . 'WHERE start_date >= ? AND start_date < ? '
            . 'ORDER BY start_date'
        );
        $stmt->execute([
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 00:00:00'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $events = [];
        $now = new DateTimeImmutable('now');
        $teamStmt = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE event_uid = ?');
        foreach ($rows as $row) {
            $startDate = new DateTimeImmutable((string) $row['start_date']);
            $endDate = new DateTimeImmutable((string) $row['end_date']);
            $teamStmt->execute([$row['uid']]);
            $teams = (int) $teamStmt->fetchColumn();
            if (!(bool) $row['published']) {
                $status = 'draft';
            } elseif ($startDate > $now) {
                $status = 'scheduled';
            } elseif ($endDate < $now) {
                $status = 'finished';
            } else {
                $status = 'running';
            }
            $events[] = [
                'id' => $row['uid'],
                'title' => $row['name'],
                'start' => $startDate->format(DateTimeInterface::ATOM),
                'end' => $endDate->format(DateTimeInterface::ATOM),
                'status' => $status,
                'teams' => $teams,
                'stations' => 0,
            ];
        }

        $upcoming = [];
        foreach ($events as $e) {
            $startDate = new DateTimeImmutable($e['start']);
            $diff = $now->diff($startDate)->days;
            if ($startDate >= $now && $diff <= 7) {
                $upcoming[] = [
                    'id' => $e['id'],
                    'title' => $e['title'],
                    'start' => $e['start'],
                    'days' => $diff,
                ];
            }
        }

        $totalCount = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $upcomingCount = (int) $pdo->query('SELECT COUNT(*) FROM events WHERE start_date > NOW()')->fetchColumn();
        $pastCount = (int) $pdo->query('SELECT COUNT(*) FROM events WHERE end_date < NOW()')->fetchColumn();

        $stats = [
            'eventCount' => $totalCount,
            'upcomingCount' => $upcomingCount,
            'pastCount' => $pastCount,
            'teamsWithoutQr' => 0,
            'resultsAwaitingReview' => 0,
        ];

        $usageStmt = $pdo->query('SELECT COUNT(*) FROM events');
        $eventCount = (int) $usageStmt->fetchColumn();
        $catCount = (int) $pdo->query('SELECT COUNT(*) FROM catalogs')->fetchColumn();
        $qCount = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
        $domainType = (string) $request->getAttribute('domainType');
        $host = $request->getUri()->getHost();
        $sub = $domainType === 'main' ? 'main' : explode('.', $host)[0];
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $plan = $tenantSvc->getPlanBySubdomain($sub);
        $limits = $tenantSvc->getLimitsBySubdomain($sub);
        $payload = [
            'period' => $start->format('Y-m'),
            'today' => $now->format('Y-m-d'),
            'events' => $events,
            'upcoming' => $upcoming,
            'stats' => $stats,
            'subscription' => [
                'plan' => $plan,
                'limits' => $limits,
                'usage' => [
                    'events' => $eventCount,
                    'catalogs' => $catCount,
                    'questions' => $qCount,
                ],
            ],
        ];
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/pages', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/management', AdminController::class)->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/rag-chat', AdminController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/admin/profile', AdminController::class)
        ->add(new RoleAuthMiddleware(...Roles::ADMIN_UI))
        ->add(new CsrfMiddleware());
    $app->get('/admin/subscription', AdminController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/subscription/portal', SubscriptionController::class)->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/subscription/status', function (Request $request, Response $response) {
        $domainType = (string) $request->getAttribute('domainType');
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $domainType === 'main'
            ? $tenantSvc->getMainTenant()
            : $tenantSvc->getBySubdomain(explode('.', $request->getUri()->getHost())[0]);
        $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
        $payload = [];
        if ($customerId !== '' && StripeService::isConfigured()['ok']) {
            $service = new StripeService();
            try {
                $info = $service->getActiveSubscription($customerId);
                if ($info !== null) {
                    $payload = $info;
                }
            } catch (\Throwable $e) {
                // ignore errors
            }
        }
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->get('/admin/subscription/invoices', function (Request $request, Response $response) {
        $domainType = (string) $request->getAttribute('domainType');
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $domainType === 'main'
            ? $tenantSvc->getMainTenant()
            : $tenantSvc->getBySubdomain(explode('.', $request->getUri()->getHost())[0]);
        $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
        $payload = [];
        if ($customerId !== '' && StripeService::isConfigured()['ok']) {
            $service = new StripeService();
            try {
                $payload = $service->listInvoices($customerId);
            } catch (\Throwable $e) {
                // ignore errors
            }
        }
        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->post('/admin/subscription/toggle', function (Request $request, Response $response) {
        $domainType = $request->getAttribute('domainType');
        $target = 'main';
        if ($domainType !== 'main') {
            $sub = explode('.', $request->getUri()->getHost())[0];
            if ($sub !== 'demo') {
                return $response->withStatus(403);
            }
            $target = 'demo';
        }
        $data = json_decode((string) $request->getBody(), true);
        $plan = $data['plan'] ?? null;
        if ($plan === '') {
            $plan = null;
        }
        $allowed = [null, Plan::STARTER->value, Plan::STANDARD->value, Plan::PROFESSIONAL->value];
        if (!in_array($plan, $allowed, true)) {
            return $response->withStatus(400);
        }
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        try {
            $tenant = $tenantSvc->getBySubdomain($target) ?? [];
            $customerId = $tenant['stripe_customer_id'] ?? null;
            if ($customerId !== null && $customerId !== '') {
                $stripeSvc = new StripeService();
                if ($plan !== null) {
                    $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
                    $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
                    $map = [
                        'starter' => getenv($prefix . 'PRICE_STARTER') ?: '',
                        'standard' => getenv($prefix . 'PRICE_STANDARD') ?: '',
                        'professional' => getenv($prefix . 'PRICE_PROFESSIONAL') ?: '',
                    ];
                    $priceId = $map[$plan];
                    if ($priceId === '') {
                        throw new \RuntimeException('price-id-missing');
                    }
                    $stripeSvc->updateSubscriptionForCustomer($customerId, $priceId);
                } else {
                    $stripeSvc->cancelSubscriptionForCustomer($customerId);
                }
            }
            $tenantSvc->updateProfile($target, ['plan' => $plan]);
        } catch (\Throwable $e) {
            error_log('Stripe subscription update failed: ' . $e->getMessage());
            return $response->withStatus(500);
        }
        $response->getBody()->write((string) json_encode(['plan' => $plan]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->post(
        '/admin/subscription/checkout',
        AdminSubscriptionCheckoutController::class
    )->add(new RoleAuthMiddleware(...Roles::ADMIN_UI))->add(new CsrfMiddleware());
    $app->get(
        '/admin/subscription/checkout/{id}',
        StripeSessionController::class
    )->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->post('/admin/profile', function (Request $request, Response $response) {
        $controller = new ProfileController();
        return $controller->update($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI))->add(new CsrfMiddleware());
    $app->post('/admin/profile/welcome', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'tenant') {
            return $response->withStatus(403);
        }
        $uri = $request->getUri();
        $sub = explode('.', $uri->getHost())[0];
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $tenantSvc->getBySubdomain($sub);
        if ($tenant === null || ($tenant['imprint_email'] ?? '') === '') {
            return $response->withStatus(404);
        }
        $email = (string) $tenant['imprint_email'];
        $pdo = Database::connectWithSchema($sub);
        MigrationRuntime::ensureUpToDate($pdo, __DIR__ . '/../migrations', 'schema:' . $sub);
        $userService = new UserService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $admin = $userService->getByUsername('admin');
        $randomPass = bin2hex(random_bytes(16));
        if ($admin === null) {
            $userService->create('admin', $randomPass, $email, Roles::ADMIN);
            $admin = $userService->getByUsername('admin');
        } else {
            $userService->updatePassword((int) $admin['id'], $randomPass);
            $userService->setEmail((int) $admin['id'], $email);
        }
        if ($admin === null) {
            return $response->withStatus(500);
        }
        $resetService = new PasswordResetService($pdo);
        $token = $resetService->createToken((int) $admin['id']);

        $providerManager = $request->getAttribute('mailProviderManager');
        if (!$providerManager instanceof MailProviderManager) {
            $providerManager = new MailProviderManager(new SettingsService(Database::connectFromEnv()));
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$providerManager->isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $providerManager, $auditLogger);
        }
        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $domain = $mainDomain ? sprintf('%s.%s', $sub, $mainDomain) : $uri->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $mailer->sendWelcome($email, $domain, $link);

        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->get('/admin/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new AdminController();
        return $controller($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/catalogs', AdminController::class)
        ->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/admin/catalogs/data', function (Request $request, Response $response) {
        $controller = $request->getAttribute('adminCatalogController');
        return $controller->catalogs($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/admin/catalogs/sample', function (Request $request, Response $response) {
        $controller = $request->getAttribute('adminCatalogController');
        return $controller->sample($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->get('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new PageController();
        return $controller->edit($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/pages/{pageId:[0-9]+}/wiki', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->index($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/settings', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->updateSettings($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->saveArticle($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/status', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->updateStatus($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/start', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->updateStartDocument($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/duplicate', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->duplicate($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->showArticle($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}/download', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->download($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->delete('/admin/pages/{pageId:[0-9]+}/wiki/articles/{articleId:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages/{pageId:[0-9]+}/wiki/articles/sort', function (Request $request, Response $response, array $args) {
        $controller = new MarketingPageWikiController();

        return $controller->sort($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new PageController();
        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->delete('/admin/pages/{slug}', function (Request $request, Response $response, array $args) {
        $controller = new PageController();
        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/pages', function (Request $request, Response $response) {
        $controller = new PageController();
        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/admin/landing-news', function (Request $request, Response $response) {
        $controller = new AdminLandingNewsController();
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->get('/admin/landing-news/create', function (Request $request, Response $response) {
        $controller = new AdminLandingNewsController();
        return $controller->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->post('/admin/landing-news', function (Request $request, Response $response) {
        $controller = new AdminLandingNewsController();
        return $controller->store($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->get('/admin/landing-news/{id:\d+}', function (Request $request, Response $response, array $args) {
        $controller = new AdminLandingNewsController();
        return $controller->edit($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));
    $app->post('/admin/landing-news/{id:\d+}', function (Request $request, Response $response, array $args) {
        $controller = new AdminLandingNewsController();
        return $controller->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());
    $app->post('/admin/landing-news/{id:\d+}/delete', function (Request $request, Response $response, array $args) {
        $controller = new AdminLandingNewsController();
        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/admin/landingpage/seo', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(404);
        }
        $controller = new LandingpageController();
        return $controller->page($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/landingpage/seo', function (Request $request, Response $response) {
        $controller = new LandingpageController();
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->page($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));

    $app->get('/results.json', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->get($request, $response);
    });

    $app->get('/question-results.json', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->getQuestions($request, $response);
    });

    $app->get('/event/{slug}/dashboard/{token}', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('dashboardController')->view($request, $response, $args);
    });

    $app->get('/results/download', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->download($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));

    $app->get('/results.pdf', function (Request $request, Response $response) {
        $team = $request->getQueryParams()['team'] ?? null;
        if ($team !== null) {
            $request = $request->withAttribute('team', (string) $team);
        }

        return $request->getAttribute('resultController')->pdf($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::ANALYST));

    $app->post('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->post($request, $response);
    });

    $app->get('/api/players', function (Request $request, Response $response) {
        $params = $request->getQueryParams();
        $eventUid = trim((string) ($params['event_uid'] ?? ''));
        if ($eventUid === '' && isset($_SESSION['event_uid'])) {
            $eventUid = trim((string) $_SESSION['event_uid']);
        }
        if ($eventUid === '') {
            $config = $request->getAttribute('configService');
            if ($config instanceof ConfigService) {
                $eventUid = $config->getActiveEventUid();
            }
        }
        $playerUid = trim((string) ($params['player_uid'] ?? ''));

        /** @var PlayerService $playerService */
        $playerService = $request->getAttribute('playerService');
        $player = $playerService->find($eventUid, $playerUid);
        if ($player === null) {
            return $response->withStatus(404);
        }

        $payload = ['player_name' => $player['player_name']];
        if ($player['contact_email'] !== null) {
            $payload['contact_email'] = $player['contact_email'];
        }
        if ($player['consent_granted_at'] !== null) {
            $payload['consent_granted_at'] = $player['consent_granted_at'];
        }

        $response->getBody()->write((string) json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/players', function (Request $request, Response $response) {
        $data = (array) $request->getParsedBody();
        $eventUid = trim((string) ($data['event_uid'] ?? ''));
        if ($eventUid === '' && isset($_SESSION['event_uid'])) {
            $eventUid = trim((string) $_SESSION['event_uid']);
        }
        if ($eventUid === '') {
            $config = $request->getAttribute('configService');
            if ($config instanceof ConfigService) {
                $eventUid = $config->getActiveEventUid();
            }
        }
        $playerName = trim((string) ($data['player_name'] ?? ''));
        $playerUid = trim((string) ($data['player_uid'] ?? ''));
        $hasEmail = array_key_exists('contact_email', $data);
        $hasConsent = array_key_exists('consent_granted_at', $data);
        $contactEmail = null;
        $consentGrantedAt = null;

        if ($hasEmail) {
            $rawEmail = $data['contact_email'];
            if (is_string($rawEmail)) {
                $rawEmail = trim($rawEmail);
            }

            if ($rawEmail === '' || $rawEmail === null) {
                $contactEmail = null;
            } elseif (!is_string($rawEmail)) {
                return $response->withStatus(400);
            } else {
                $sanitizedEmail = mb_strtolower($rawEmail);
                if (filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL) === false) {
                    return $response->withStatus(400);
                }
                $contactEmail = $sanitizedEmail;
            }
        }

        if ($hasConsent) {
            $rawConsent = $data['consent_granted_at'];
            if (is_string($rawConsent)) {
                $rawConsent = trim($rawConsent);
            }

            if ($rawConsent === '' || $rawConsent === null) {
                $consentGrantedAt = null;
            } elseif (!is_string($rawConsent)) {
                return $response->withStatus(400);
            } else {
                try {
                    $consentGrantedAt = new DateTimeImmutable($rawConsent);
                } catch (\Exception $exception) {
                    return $response->withStatus(400);
                }
            }
        }

        if ($contactEmail !== null && $consentGrantedAt === null) {
            return $response->withStatus(400);
        }

        if ($consentGrantedAt !== null && !$hasEmail) {
            return $response->withStatus(400);
        }

        /** @var PlayerService $playerService */
        $playerService = $request->getAttribute('playerService');

        try {
            $playerService->save(
                $eventUid,
                $playerName,
                $playerUid,
                $contactEmail,
                $consentGrantedAt,
                $hasEmail || $hasConsent
            );
        } catch (UsernameBlockedException $exception) {
            $response->getBody()->write((string) json_encode(['error' => 'name_blocked']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (PlayerNameConflictException $exception) {
            $response->getBody()->write((string) json_encode(['error' => 'name_taken']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        }

        return $response->withStatus(204);
    });

    $app->post('/api/player-contact', function (Request $request, Response $response) {
        /** @var PlayerContactController|null $controller */
        $controller = $request->getAttribute('playerContactController');
        if (!$controller instanceof PlayerContactController) {
            return $response->withStatus(500);
        }

        return $controller->request($request, $response);
    });

    $app->post('/api/player-contact/confirm', function (Request $request, Response $response) {
        /** @var PlayerContactController|null $controller */
        $controller = $request->getAttribute('playerContactController');
        if (!$controller instanceof PlayerContactController) {
            return $response->withStatus(500);
        }

        return $controller->confirm($request, $response);
    });

    $app->delete('/api/player-contact', function (Request $request, Response $response) {
        /** @var PlayerContactController|null $controller */
        $controller = $request->getAttribute('playerContactController');
        if (!$controller instanceof PlayerContactController) {
            return $response->withStatus(500);
        }

        return $controller->delete($request, $response);
    });

    $app->get('/api/team-names/batch', function (Request $request, Response $response) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');
        return $controller->reserveBatch($request, $response);
    });

    $app->get('/api/team-names/status', function (Request $request, Response $response) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');

        return $controller->status($request, $response);
    });

    $app->post('/api/team-names/preview', function (Request $request, Response $response) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');

        return $controller->preview($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->get('/api/team-names/history', function (Request $request, Response $response) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');

        return $controller->history($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->post('/api/team-names', function (Request $request, Response $response) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');
        return $controller->reserve($request, $response);
    });

    $app->delete('/api/team-names/by-name', function (Request $request, Response $response) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');
        return $controller->releaseByName($request, $response);
    });

    $app->post('/api/team-names/{token}/confirm', function (Request $request, Response $response, array $args) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');
        return $controller->confirm($request, $response, $args);
    });

    $app->delete('/api/team-names/{token}', function (Request $request, Response $response, array $args) {
        /** @var TeamNameController $controller */
        $controller = $request->getAttribute('teamNameController');
        return $controller->release($request, $response, $args);
    });

    $app->delete('/results', function (Request $request, Response $response) {
        return $request->getAttribute('resultController')->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/config.json', function (Request $request, Response $response) {
        return $request->getAttribute('configController')->get($request, $response);
    });

    $app->get('/events/{uid}/config.json', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('configController')->getByEvent($request, $response, $args);
    });

    $app->post('/config.json', function (Request $request, Response $response) {
        return $request->getAttribute('configController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));

    $app->get('/settings.json', function (Request $request, Response $response) {
        return $request->getAttribute('settingsController')->get($request, $response);
    });

    $app->post('/settings.json', function (Request $request, Response $response) {
        return $request->getAttribute('settingsController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/domain-start-pages', function (Request $request, Response $response) {
        /** @var DomainStartPageController $controller */
        $controller = $request->getAttribute('domainStartPageController');
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/domain-start-pages', function (Request $request, Response $response) {
        /** @var DomainStartPageController $controller */
        $controller = $request->getAttribute('domainStartPageController');
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/marketing-domains', function (Request $request, Response $response) {
        /** @var DomainStartPageController $controller */
        $controller = $request->getAttribute('domainStartPageController');
        return $controller->createMarketingDomain($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/domain-start-pages/certificate', function (Request $request, Response $response) {
        /** @var DomainStartPageController $controller */
        $controller = $request->getAttribute('domainStartPageController');
        return $controller->provisionCertificate($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->patch('/admin/marketing-domains/{id}', function (Request $request, Response $response, array $args) {
        /** @var DomainStartPageController $controller */
        $controller = $request->getAttribute('domainStartPageController');
        return $controller->updateMarketingDomain($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->delete('/admin/marketing-domains/{id}', function (Request $request, Response $response, array $args) {
        /** @var DomainStartPageController $controller */
        $controller = $request->getAttribute('domainStartPageController');
        return $controller->deleteMarketingDomain($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/domain-chat/documents', function (Request $request, Response $response) {
        /** @var DomainChatKnowledgeController $controller */
        $controller = $request->getAttribute('domainChatController');
        return $controller->list($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/domain-chat/index', function (Request $request, Response $response) {
        /** @var DomainChatKnowledgeController $controller */
        $controller = $request->getAttribute('domainChatController');
        return $controller->download($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/domain-chat/documents', function (Request $request, Response $response) {
        /** @var DomainChatKnowledgeController $controller */
        $controller = $request->getAttribute('domainChatController');
        return $controller->upload($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->delete('/admin/domain-chat/documents/{id}', function (Request $request, Response $response, array $args) {
        /** @var DomainChatKnowledgeController $controller */
        $controller = $request->getAttribute('domainChatController');
        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/domain-chat/wiki-selection', function (Request $request, Response $response) {
        /** @var DomainChatKnowledgeController $controller */
        $controller = $request->getAttribute('domainChatController');
        return $controller->updateWikiSelection($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/domain-chat/rebuild', function (Request $request, Response $response) {
        /** @var DomainChatKnowledgeController $controller */
        $controller = $request->getAttribute('domainChatController');
        return $controller->rebuild($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/domain-contact-template/{domain}', function (Request $request, Response $response, array $args) {
        /** @var DomainContactTemplateController $controller */
        $controller = $request->getAttribute('domainContactTemplateController');
        return $controller->show($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/domain-contact-template', function (Request $request, Response $response) {
        /** @var DomainContactTemplateController $controller */
        $controller = $request->getAttribute('domainContactTemplateController');
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/marketing-newsletter-configs', function (Request $request, Response $response) {
        /** @var MarketingNewsletterConfigController $controller */
        $controller = $request->getAttribute('marketingNewsletterConfigController');
        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/admin/marketing-newsletter-configs', function (Request $request, Response $response) {
        /** @var MarketingNewsletterConfigController $controller */
        $controller = $request->getAttribute('marketingNewsletterConfigController');
        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/admin/mail-providers', function (Request $request, Response $response) {
        $controller = $request->getAttribute('mailProviderController');
        if (!$controller instanceof MailProviderController) {
            return $response->withStatus(500);
        }

        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/mail-providers', function (Request $request, Response $response) {
        $controller = $request->getAttribute('mailProviderController');
        if (!$controller instanceof MailProviderController) {
            return $response->withStatus(500);
        }

        return $controller->save($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/mail-providers/test', function (Request $request, Response $response) {
        $controller = $request->getAttribute('mailProviderController');
        if (!$controller instanceof MailProviderController) {
            return $response->withStatus(500);
        }

        return $controller->testConnection($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/admin/username-blocklist', function (Request $request, Response $response) {
        $controller = $request->getAttribute('usernameBlocklistController');
        if (!$controller instanceof UsernameBlocklistController) {
            return $response->withStatus(500);
        }

        return $controller->index($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/username-blocklist', function (Request $request, Response $response) {
        $controller = $request->getAttribute('usernameBlocklistController');
        if (!$controller instanceof UsernameBlocklistController) {
            return $response->withStatus(500);
        }

        return $controller->store($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->post('/admin/username-blocklist/import', function (Request $request, Response $response) {
        $controller = $request->getAttribute('usernameBlocklistController');
        if (!$controller instanceof UsernameBlocklistController) {
            return $response->withStatus(500);
        }

        return $controller->import($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->delete('/admin/username-blocklist/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
        $controller = $request->getAttribute('usernameBlocklistController');
        if (!$controller instanceof UsernameBlocklistController) {
            return $response->withStatus(500);
        }

        return $controller->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/catalog/questions/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->getQuestions($req, $response, $args);
    });

    $app->get('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->get($req, $response, $args);
    });

    $app->post('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->post($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->delete('/kataloge/{file}/{index}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file'])
            ->withAttribute('index', $args['index']);
        return $request->getAttribute('catalogController')->deleteQuestion($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->put('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->create($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));

    $app->delete('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('catalogController')->delete($req, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::CATALOG_EDITOR));
    $app->get('/events.json', function (Request $request, Response $response) {
        return $request->getAttribute('eventController')->get($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->post('/events.json', function (Request $request, Response $response) {
        return $request->getAttribute('eventController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->get('/admin/event/{id}', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('eventConfigController')->show($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->map(['PATCH', 'POST'], '/admin/event/{id}', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('eventConfigController')->update($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER));
    $app->post('/admin/event/{id}/dashboard-token', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('eventConfigController')->rotateToken($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::EVENT_MANAGER))->add(new CsrfMiddleware());

    $app->post('/invite', function (Request $request, Response $response) {
        $pdo = $request->getAttribute('pdo');
        $twig = Twig::fromRequest($request)->getEnvironment();
        $providerManager = $request->getAttribute('mailProviderManager');
        if (!$providerManager instanceof MailProviderManager) {
            $providerManager = new MailProviderManager(new SettingsService(Database::connectFromEnv()));
        }
        if (!$providerManager->isConfigured()) {
            return $response->withStatus(503);
        }
        $mailer = new MailService($twig, $providerManager);
        $service = new InvitationService($pdo);
        $controller = new InvitationController($service, $mailer);
        return $controller->send($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->create($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->get('/tenants/export', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->export($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants/report', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->report($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants/{subdomain}', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->exists($request, $response, $args);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->delete('/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->post('/tenants/sync', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->sync($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->listHtml($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->get('/tenants.json', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        return $request->getAttribute('tenantController')->list($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN));

    $app->post('/tenants/{subdomain}/welcome', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $sub = preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) ($args['subdomain'] ?? '')));
        $base = Database::connectFromEnv();
        $tenantSvc = new TenantService($base);
        $tenant = $tenantSvc->getBySubdomain($sub);
        if ($tenant === null || ($tenant['imprint_email'] ?? '') === '') {
            return $response->withStatus(404);
        }
        $email = (string) $tenant['imprint_email'];
        $pdo = Database::connectWithSchema($sub);
        MigrationRuntime::ensureUpToDate($pdo, __DIR__ . '/../migrations', 'schema:' . $sub);
        $userService = new UserService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $admin = $userService->getByUsername('admin');
        $randomPass = bin2hex(random_bytes(16));
        if ($admin === null) {
            $userService->create('admin', $randomPass, $email, Roles::ADMIN);
            $admin = $userService->getByUsername('admin');
        } else {
            $userService->updatePassword((int) $admin['id'], $randomPass);
            $userService->setEmail((int) $admin['id'], $email);
        }
        if ($admin === null) {
            return $response->withStatus(500);
        }
        $resetService = new PasswordResetService($pdo);
        $token = $resetService->createToken((int) $admin['id']);

        $providerManager = $request->getAttribute('mailProviderManager');
        if (!$providerManager instanceof MailProviderManager) {
            $providerManager = new MailProviderManager(new SettingsService(Database::connectFromEnv()));
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$providerManager->isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $providerManager, $auditLogger);
        }
        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $domain = $mainDomain ? sprintf('%s.%s', $sub, $mainDomain) : $request->getUri()->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $mailer->sendWelcome($email, $domain, $link);

        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::ADMIN))->add(new CsrfMiddleware());

    $app->get('/teams.json', function (Request $request, Response $response) {
        return $request->getAttribute('teamController')->get($request, $response);
    });
    $app->post('/teams.json', function (Request $request, Response $response) {
        return $request->getAttribute('teamController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::TEAM_MANAGER));
    $app->delete('/teams.json', function (Request $request, Response $response) {
        return $request->getAttribute('teamController')->delete($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::TEAM_MANAGER));
    $app->get('/users.json', function (Request $request, Response $response) {
        return $request->getAttribute('userController')->get($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));
    $app->post('/users.json', function (Request $request, Response $response) {
        return $request->getAttribute('userController')->post($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));
    $app->post('/password', function (Request $request, Response $response) {
        return $request->getAttribute('passwordController')->post($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ALL));
    $app->post('/password/reset/request', function (Request $request, Response $response) {
        return $request->getAttribute('passwordResetController')->request($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->post('/password/reset/confirm', function (Request $request, Response $response) {
        return $request->getAttribute('passwordResetController')->confirm($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->post('/password/set', function (Request $request, Response $response) {
        return $request->getAttribute('passwordResetController')->confirm($request, $response);
    })->add(new RateLimitMiddleware(3, 3600))->add(new CsrfMiddleware());
    $app->post('/import', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/restore-default', function (Request $request, Response $response) {
        return $request->getAttribute('importController')->restoreDefaults($request, $response);
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT));

    $app->post('/export-default', function (Request $request, Response $response) {
        return $request->getAttribute('exportController')->exportDefaults($request, $response);
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/tenant-welcome', function (Request $request, Response $response) {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data['schema'], $data['email'])) {
            return $response->withStatus(400);
        }
        $schema = preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string) $data['schema']));
        if ($schema === '' || $schema === 'public') {
            return $response->withStatus(400);
        }
        $email = (string) $data['email'];
        $pdo = Database::connectWithSchema($schema);
        MigrationRuntime::ensureUpToDate($pdo, __DIR__ . '/../migrations', 'schema:' . $schema);
        $userService = new UserService($pdo);
        $auditLogger = new AuditLogger($pdo);
        $admin = $userService->getByUsername('admin');
        $randomPass = bin2hex(random_bytes(16));
        if ($admin === null) {
            $userService->create('admin', $randomPass, $email, Roles::ADMIN);
            $admin = $userService->getByUsername('admin');
        } else {
            $userService->updatePassword((int)$admin['id'], $randomPass);
            $userService->setEmail((int)$admin['id'], $email);
        }
        if ($admin === null) {
            return $response->withStatus(500);
        }

        $resetService = new PasswordResetService($pdo);
        $token = $resetService->createToken((int)$admin['id']);

        $mainDomain = getenv('MAIN_DOMAIN') ?: getenv('DOMAIN');
        $twig = Twig::fromRequest($request)->getEnvironment();
        $providerManager = $request->getAttribute('mailProviderManager');
        if (!$providerManager instanceof MailProviderManager) {
            $providerManager = new MailProviderManager(new SettingsService(Database::connectFromEnv()));
        }
        if (!$providerManager->isConfigured()) {
            return $response->withStatus(503);
        }
        $mailer = new MailService($twig, $providerManager, $auditLogger);
        $domain = $mainDomain ? sprintf('%s.%s', $schema, $mainDomain) : $request->getUri()->getHost();
        $link = sprintf('https://%s/password/set?token=%s&next=%%2Fadmin', $domain, urlencode($token));
        $mailer->sendWelcome($email, $domain, $link);

        $tenantBase = Database::connectFromEnv();
        $tenantService = new TenantService($tenantBase);
        $tenantService->updateOnboardingState($schema, TenantService::ONBOARDING_COMPLETED);

        return $response->withStatus(204);
    })->add(new RoleAuthMiddleware(Roles::SERVICE_ACCOUNT));
    $app->post('/import/{name}', function (Request $request, Response $response, array $args) {
        return $request
            ->getAttribute('importController')
            ->import($request->withAttribute('name', $args['name']), $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/export', function (Request $request, Response $response) {
        return $request->getAttribute('exportController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/backups', function (Request $request, Response $response) {
        return $request->getAttribute('backupController')->index($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/backups/{name}/download', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('backupController')->download($request, $response, $args);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/backups/{name}/restore', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('backupController')->restore($request, $response, $args);
    })->add(new RoleAuthMiddleware('admin'));
    $app->delete('/backups/{name}', function (Request $request, Response $response, array $args) {
        return $request->getAttribute('backupController')->delete($request, $response, $args);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/qr.png', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->image($request, $response);
    });
    $app->get('/qr.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->pdf($request, $response);
    });
    $app->get('/qr/catalog', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->catalog($request, $response);
    });
    // Team QR codes
    $app->get('/qr/team', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->team($request, $response);
    });
    $app->get('/qr/event', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->event($request, $response);
    });
    $app->get('/invites.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('qrController')->pdfAll($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/admin/reports/catalog-stickers.pdf', function (Request $request, Response $response) {
        return $request->getAttribute('catalogStickerController')->pdf($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));

    $app->get('/admin/sticker-settings', function (Request $request, Response $response) {
        return $request->getAttribute('catalogStickerController')->getSettings($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));
    $app->post('/admin/sticker-settings', function (Request $request, Response $response) {
        return $request->getAttribute('catalogStickerController')->saveSettings($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));

    $app->post('/admin/sticker-background', function (Request $request, Response $response) {
        return $request->getAttribute('catalogStickerController')->uploadBackground($request, $response);
    })->add(new RoleAuthMiddleware(...Roles::ADMIN_UI));

    $app->get('/uploads/{file:.+}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('file', $args['file']);
        return $request->getAttribute('globalMediaController')->get($req, $response);
    });

    $app->get('/events/{uid}/images/{file}', function (Request $request, Response $response, array $args) {
        $req = $request
            ->withAttribute('uid', $args['uid'])
            ->withAttribute('file', $args['file']);
        return $request->getAttribute('eventImageController')->get($req, $response);
    });

    $app->get('/admin/{path:.*}', function (Request $request, Response $response) {
        $base = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();
        return $response->withHeader('Location', $base . '/admin/dashboard')->withStatus(302);
    });
    $app->get('/{file:logo(?:-[\w-]+)?\.png}', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->get($request->withAttribute('ext', 'png'), $response);
    });
    $app->post('/logo.png', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/{file:logo(?:-[\w-]+)?\.webp}', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->get($request->withAttribute('ext', 'webp'), $response);
    });
    $app->post('/logo.webp', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/{file:logo(?:-[\w-]+)?\.svg}', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->get($request->withAttribute('ext', 'svg'), $response);
    });
    $app->post('/logo.svg', function (Request $request, Response $response) {
        return $request->getAttribute('logoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));

    $app->get('/{file:qrlogo(?:-[\w-]+)?\.png}', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->get($request->withAttribute('ext', 'png'), $response);
    });
    $app->post('/qrlogo.png', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->get('/{file:qrlogo(?:-[\w-]+)?\.webp}', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->get($request->withAttribute('ext', 'webp'), $response);
    });
    $app->post('/qrlogo.webp', function (Request $request, Response $response) {
        return $request->getAttribute('qrLogoController')->post($request, $response);
    })->add(new RoleAuthMiddleware('admin'));

    $app->get('/catalog/{slug}/design', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('slug', $args['slug']);
        return $request->getAttribute('catalogDesignController')->get($req, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/catalog/{slug}/design', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('slug', $args['slug']);
        return $request->getAttribute('catalogDesignController')->post($req, $response);
    })->add(new RoleAuthMiddleware('admin'));
    $app->post('/photos', function (Request $request, Response $response) {
        return $request->getAttribute('evidenceController')->post($request, $response);
    });
    $app->post('/photos/rotate', function (Request $request, Response $response) {
        return $request->getAttribute('evidenceController')->rotate($request, $response);
    });
    $app->get('/photo/{team}/{file}', function (Request $request, Response $response, array $args) {
        $req = $request->withAttribute('team', $args['team'])->withAttribute('file', $args['file']);
        return $request->getAttribute('evidenceController')->get($req, $response);
    });
    $app->get('/ranking', function (Request $request, Response $response) {
        return $request->getAttribute('rankingController')($request, $response);
    });
    $app->get('/summary', function (Request $request, Response $response) {
        return $request->getAttribute('summaryController')($request, $response);
    });

    $app->post('/nginx-reload', function (Request $request, Response $response) {
        $token = $request->getHeaderLine('X-Token');
        $expected = $_ENV['NGINX_RELOAD_TOKEN'] ?? 'changeme';
        $reloaderUrl = $_ENV['NGINX_RELOADER_URL'] ?? 'http://nginx-reloader:8080/reload';

        if ($token !== $expected) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }

        $client = $request->getAttribute('httpClient');
        if (!$client instanceof Client) {
            $client = new Client();
        }
        try {
            $client->post($reloaderUrl, [
                'headers' => ['X-Token' => $expected],
            ]);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Reload failed',
                'details' => $e->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'nginx reloaded']));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/tenants/{slug}/onboard', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            $response->getBody()->write(json_encode(['error' => 'forbidden']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $slug = strtolower((string) ($args['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid slug']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
        $logPath = __DIR__ . '/../logs/onboarding.log';
        $log = is_file($logPath) ? (string) file_get_contents($logPath) : '';
        $base = Database::connectFromEnv();
        $tenantService = new TenantService($base);
        $tenant = $tenantService->getBySubdomain($slug);

        if ($tenant === null) {
            $response->getBody()->write(json_encode([
                'error' => 'tenant-missing',
                'log' => $log,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_PROVISIONING);

        $singleContainerFlag = getenv('TENANT_SINGLE_CONTAINER');
        $singleContainerEnabled = filter_var((string) $singleContainerFlag, FILTER_VALIDATE_BOOLEAN);

        if ($singleContainerEnabled) {
            $baseDomain = trim((string) (getenv('MAIN_DOMAIN') ?: getenv('DOMAIN')));
            $certDir = realpath(__DIR__ . '/../certs') ?: (__DIR__ . '/../certs');

            if ($baseDomain === '') {
                $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
                $response->getBody()->write(json_encode([
                    'error' => 'wildcard-domain-missing',
                    'log' => $log,
                ]));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
            }

            $certPath = $certDir . '/' . $baseDomain . '.crt';
            $keyPath = $certDir . '/' . $baseDomain . '.key';

            if (!is_file($certPath) || !is_file($keyPath)) {
                $overrideScript = getenv('PROVISION_WILDCARD_SCRIPT');
                if ($overrideScript !== false && $overrideScript !== '') {
                    $provisionScript = $overrideScript;
                } else {
                    $provisionScript = realpath(__DIR__ . '/../scripts/provision_wildcard.sh');
                }

                if ($provisionScript === false || !is_file($provisionScript)) {
                    $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
                    $response->getBody()->write(json_encode([
                        'error' => 'wildcard-script-missing',
                        'log' => $log,
                    ]));

                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
                }

                if (!is_dir(dirname($logPath))) {
                    mkdir(dirname($logPath), 0775, true);
                }

                $message = sprintf('[%s] Missing wildcard certificate for "%s" – provisioning via script.', date('c'), $baseDomain);
                file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND);
                $log = (string) file_get_contents($logPath);

                $result = runSyncProcess($provisionScript, ['--domain', $baseDomain]);

                clearstatcache(true, $certPath);
                clearstatcache(true, $keyPath);

                if (!is_file($certPath) || !is_file($keyPath)) {
                    $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
                    $response->getBody()->write(json_encode([
                        'error' => 'wildcard-provisioning-failed',
                        'details' => $result['stderr'] !== '' ? $result['stderr'] : $result['stdout'],
                        'log' => (string) file_get_contents($logPath),
                    ]));

                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
                }

                $message = sprintf('[%s] Wildcard certificate ready for "%s".', date('c'), $baseDomain);
                file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND);
                $log = (string) file_get_contents($logPath);
            }

            $migrationsDir = __DIR__ . '/../migrations';

            try {
                Migrator::migrate($base, $migrationsDir);

                $stmt = $base->prepare('SELECT subdomain FROM tenants WHERE subdomain = ?');
                $stmt->execute([$slug]);
                $schema = $stmt->fetchColumn();

                if ($schema === false) {
                    $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
                    $response->getBody()->write(json_encode([
                        'error' => 'Tenant not found',
                        'log' => $log,
                    ]));

                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(404);
                }

                $pdo = Database::connectWithSchema((string) $schema);
                Migrator::migrate($pdo, $migrationsDir);
            } catch (\Throwable $e) {
                $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
                if (!is_dir(dirname($logPath))) {
                    mkdir(dirname($logPath), 0775, true);
                }
                $message = sprintf(
                    '[%s] Failed to migrate tenant "%s" in single container mode: %s',
                    date('c'),
                    $slug,
                    $e->getMessage()
                );
                file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND);
                $log = (string) file_get_contents($logPath);
                $response->getBody()->write(json_encode([
                    'error' => 'Failed to onboard tenant',
                    'log' => $log,
                    'details' => $e->getMessage(),
                ]));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
            }

            $message = sprintf('[%s] Single container mode active – skipped docker onboarding for "%s".', date('c'), $slug);
            if (!is_dir(dirname($logPath))) {
                mkdir(dirname($logPath), 0775, true);
            }
            file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND);
            $log = (string) file_get_contents($logPath);

            $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_PROVISIONED);
            $payload = [
                'status' => 'completed',
                'tenant' => $slug,
                'log' => $log,
                'mode' => 'single-container',
            ];
            $response->getBody()->write(json_encode($payload));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $script = realpath(__DIR__ . '/../scripts/onboard_tenant.sh');

        if (!is_file($script)) {
            $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
            $response->getBody()->write(json_encode([
                'error' => 'Onboard script not found',
                'log' => $log,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $result = runSyncProcess($script, [$slug]);
        $tenantDir = __DIR__ . '/../tenants/' . $slug;
        $composeFile = $tenantDir . '/docker-compose.yml';

        if (!$result['success'] || !is_file($composeFile)) {
            $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_FAILED);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to onboard tenant',
                'stderr' => $result['stderr'],
                'log' => $log,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $tenantService->updateOnboardingState($slug, TenantService::ONBOARDING_PROVISIONED);
        $payload = ['status' => 'completed', 'tenant' => $slug, 'log' => $log];
        $response->getBody()->write(json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware(Roles::ADMIN, Roles::SERVICE_ACCOUNT))->add(new CsrfMiddleware());

    $app->delete('/api/tenants/{slug}', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = strtolower((string) ($args['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid slug']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $script = realpath(__DIR__ . '/../scripts/offboard_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Offboard script not found']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $result = runSyncProcess($script, [$slug]);
        if (!$result['success']) {
            $message = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to remove tenant',
                'message' => $message,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/renew-ssl', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $script = realpath(__DIR__ . '/../scripts/renew_ssl.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Renew script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $result = runSyncProcess($script, ['--main']);
        if (!$result['success']) {
            $message = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to renew certificate',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => 'main']));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/tenants/{slug}/renew-ssl', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = strtolower((string) ($args['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid slug']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $script = realpath(__DIR__ . '/../scripts/renew_ssl.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Renew script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $result = runSyncProcess($script, [$slug]);
        if (!$result['success']) {
            $message = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to renew certificate',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/docker/build', function (Request $request, Response $response) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $script = realpath(__DIR__ . '/../scripts/build_image.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Build script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
        $result = runSyncProcess($script);
        if (!$result['success']) {
            $message = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to build image',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
        $response->getBody()->write(json_encode(['status' => 'built']));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/tenants/{slug}/upgrade', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = strtolower((string) ($args['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid slug']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $script = realpath(__DIR__ . '/../scripts/upgrade_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Upgrade script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $result = runSyncProcess($script, [$slug]);
        if (!$result['success']) {
            $message = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to upgrade tenant',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $composeFile = $slug === 'main'
            ? realpath(__DIR__ . '/../docker-compose.yml')
            : realpath(__DIR__ . '/../tenants/' . $slug . '/docker-compose.yml');
        $service = $slug === 'main' ? 'slim' : 'app';

        $dockerCmd = ['docker', 'compose'];
        $composeCheck = runSyncProcess('docker', ['compose', 'version']);
        if (!$composeCheck['success']) {
            $composeCheck = runSyncProcess('docker-compose', ['version']);
            if (!$composeCheck['success']) {
                $response->getBody()->write(json_encode(['error' => 'docker compose not available']));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
            }
            $dockerCmd = ['docker-compose'];
        }

        $psArgs = $dockerCmd[0] === 'docker'
            ? ['compose', '-f', (string) $composeFile, '-p', $slug, 'ps', '-q', $service]
            : ['-f', (string) $composeFile, '-p', $slug, 'ps', '-q', $service];
        $psResult = runSyncProcess($dockerCmd[0], $psArgs);
        if (!$psResult['success']) {
            $message = trim($psResult['stderr'] !== '' ? $psResult['stderr'] : $psResult['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to inspect container',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
        $containerId = trim($psResult['stdout']);
        if ($containerId === '') {
            $response->getBody()->write(json_encode([
                'error' => 'Container not found',
                'slug' => $slug,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $inspect = runSyncProcess('docker', ['inspect', '-f', '{{.Config.Image}}', $containerId]);
        if (!$inspect['success']) {
            $message = trim($inspect['stderr'] !== '' ? $inspect['stderr'] : $inspect['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to read image',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
        $currentImage = trim($inspect['stdout']);

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'slug' => $slug,
            'image' => $currentImage,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->post('/api/tenants/{slug}/restart', function (Request $request, Response $response, array $args) {
        if ($request->getAttribute('domainType') !== 'main') {
            return $response->withStatus(403);
        }
        $slug = strtolower((string) ($args['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid slug']));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $script = realpath(__DIR__ . '/../scripts/restart_tenant.sh');

        if (!is_file($script)) {
            $response->getBody()->write(json_encode(['error' => 'Restart script not found']));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $result = runSyncProcess($script, [$slug]);
        if (!$result['success']) {
            $message = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            $response->getBody()->write(json_encode([
                'error' => 'Failed to restart tenant',
                'message' => $message,
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['status' => 'restarted', 'slug' => $slug]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new RoleAuthMiddleware('admin'));

    $app->get('/database', function (Request $request, Response $response) {
        $uri = $request->getUri();
        $location = 'https://adminer.' . $uri->getHost();
        return $response->withHeader('Location', $location)->withStatus(302);
    })->add(new RoleAuthMiddleware('admin'));

    $app->get(
        '/{slug:[a-z0-9-]+}',
        function (Request $request, Response $response, array $args) use ($resolveMarketingAccess) {
            [$request, $allowed] = $resolveMarketingAccess($request);
            if (!$allowed || $request->getAttribute('domainType') !== 'marketing') {
                return $response->withStatus(404);
            }
            $controller = new MarketingPageController();
            return $controller($request, $response, $args);
        }
    );
};
