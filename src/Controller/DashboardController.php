<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\NamespaceResolver;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Public facing dashboard that exposes live rankings via share tokens.
 */
class DashboardController
{
    private ConfigService $config;
    private EventService $events;

    private const DASHBOARD_ALLOWED_LAYOUTS = ['auto', 'wide', 'full'];

    private const DASHBOARD_RESULTS_SORT_OPTIONS = ['time', 'points', 'name'];

    private const DASHBOARD_RESULTS_MAX_LIMIT = 50;

    private const DASHBOARD_POINTS_LEADER_MIN_LIMIT = 1;

    private const DASHBOARD_POINTS_LEADER_MAX_LIMIT = 10;

    public function __construct(ConfigService $config, EventService $events)
    {
        $this->config = $config;
        $this->events = $events;
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $token = (string) ($args['token'] ?? '');
        $variantParam = strtolower((string) ($request->getQueryParams()['variant'] ?? ''));
        $requestedVariant = $variantParam === 'sponsor' ? 'sponsor' : 'public';
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();

        /** @var array<string, mixed>|null $event */
        $event = $this->events->getBySlug($slug, $namespace);
        if ($event === null && $slug !== '') {
            $event = $this->events->getByUid($slug, $namespace);
        }
        if ($event === null) {
            return $response->withStatus(404);
        }

        if (!array_key_exists('uid', $event)) {
            return $response->withStatus(500);
        }

        $uid = (string) $event['uid'];
        $matchedVariant = $this->config->verifyDashboardToken($uid, $token);
        if ($matchedVariant === null) {
            return $response->withStatus(403);
        }
        if ($requestedVariant === 'sponsor' && $matchedVariant !== 'sponsor') {
            return $response->withStatus(403);
        }

        $cfg = $this->config->getConfigForEvent($uid);
        if (
            $matchedVariant === 'public'
            && array_key_exists('dashboardShareEnabled', $cfg)
            && $cfg['dashboardShareEnabled'] === false
        ) {
            return $response->withStatus(403);
        }
        if (
            $matchedVariant === 'sponsor'
            && array_key_exists('dashboardSponsorEnabled', $cfg)
            && $cfg['dashboardSponsorEnabled'] === false
        ) {
            return $response->withStatus(403);
        }

        $cfg['dashboardTheme'] = $this->normalizeTheme($cfg['dashboardTheme'] ?? null);

        $moduleKey = $matchedVariant === 'sponsor' ? 'dashboardSponsorModules' : 'dashboardModules';
        $moduleConfig = $cfg[$moduleKey] ?? [];
        if ($matchedVariant === 'sponsor' && (!is_array($moduleConfig) || $moduleConfig === [])) {
            $moduleConfig = $cfg['dashboardModules'] ?? [];
        }
        $modules = $this->extractModules($moduleConfig);
        $refresh = $this->sanitizeRefreshInterval((int) ($cfg['dashboardRefreshInterval'] ?? 15));
        $infoText = (string) ($cfg['dashboardInfoText'] ?? '');
        $mediaLines = $this->extractMediaItems($cfg['dashboardMediaEmbed'] ?? '');
        $start = $this->parseDateTime((string) ($cfg['dashboardVisibilityStart'] ?? ''));
        $end = $this->parseDateTime((string) ($cfg['dashboardVisibilityEnd'] ?? ''));
        $isActive = $this->isWithinWindow($start, $end);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'dashboard.twig', [
            'event' => $event,
            'config' => $cfg,
            'dashboard' => [
                'modules' => $modules,
                'refreshInterval' => $refresh,
                'infoText' => $infoText,
                'mediaItems' => $mediaLines,
                'shareToken' => $token,
                'variant' => $matchedVariant,
                'competitionMode' => !empty($cfg['competitionMode']),
                'active' => $isActive,
                'theme' => $cfg['dashboardTheme'],
                'visibility' => [
                    'start' => $start?->format('c'),
                    'end' => $end?->format('c'),
                ],
            ],
        ]);
    }

    /**
     * @param mixed $value
     * @return array<int,array<string,mixed>>
     */
    private function extractModules($value): array
    {
        $modules = [];
        if (is_array($value)) {
            $modules = $value;
        }
        $defaults = [
            ['id' => 'header', 'enabled' => true, 'layout' => 'full'],
            [
                'id' => 'pointsLeader',
                'enabled' => true,
                'layout' => 'wide',
                'options' => ['title' => 'Platzierungen', 'limit' => 5],
            ],
            [
                'id' => 'rankings',
                'enabled' => true,
                'layout' => 'wide',
                'options' => [
                    'limit' => null,
                    'pageSize' => null,
                    'sort' => 'time',
                    'title' => 'Live-Rankings',
                    'showPlacement' => false,
                ],
            ],
            [
                'id' => 'results',
                'enabled' => true,
                'layout' => 'full',
                'options' => [
                    'limit' => null,
                    'pageSize' => null,
                    'sort' => 'time',
                    'title' => 'Ergebnisliste',
                    'showPlacement' => false,
                ],
            ],
            [
                'id' => 'wrongAnswers',
                'enabled' => false,
                'layout' => 'auto',
                'options' => ['title' => 'Falsch beantwortete Fragen'],
            ],
            [
                'id' => 'infoBanner',
                'enabled' => false,
                'layout' => 'auto',
                'options' => ['title' => 'Hinweise'],
            ],
            [
                'id' => 'rankingQr',
                'enabled' => false,
                'layout' => 'auto',
                'options' => ['title' => 'Ranking-QR'],
            ],
            [
                'id' => 'qrCodes',
                'enabled' => false,
                'layout' => 'auto',
                'options' => ['catalogs' => [], 'title' => 'Katalog-QR-Codes'],
            ],
            [
                'id' => 'media',
                'enabled' => false,
                'layout' => 'auto',
                'options' => ['title' => 'Highlights'],
            ],
        ];

        if ($modules === []) {
            return $defaults;
        }

        $allowed = [];
        foreach ($defaults as $module) {
            $allowed[$module['id']] = $module;
        }

        $normalized = [];
        $seen = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }
            $id = isset($module['id']) ? (string) $module['id'] : '';
            if ($id === '' || !isset($allowed[$id]) || isset($seen[$id])) {
                continue;
            }
            $base = $allowed[$id];
            $baseLayout = (string) $base['layout'];
            $layout = isset($module['layout']) ? (string) $module['layout'] : $baseLayout;
            if (!in_array($layout, self::DASHBOARD_ALLOWED_LAYOUTS, true)) {
                $layout = $baseLayout;
            }

            $baseOptionsRaw = $base['options'] ?? null;
            $baseOptions = is_array($baseOptionsRaw) ? $baseOptionsRaw : [];
            $optionsRaw = $module['options'] ?? null;
            $options = is_array($optionsRaw) ? $optionsRaw : [];

            $entry = ['id' => $id, 'enabled' => !empty($module['enabled']), 'layout' => $layout];
            if ($id === 'rankings' || $id === 'results') {
                $limit = $this->normalizeResultsLimit($options['limit'] ?? null);
                if ($limit === null) {
                    $limit = $this->normalizeResultsLimit($baseOptions['limit'] ?? null);
                }
                $pageSize = $this->normalizeResultsPageSize($options['pageSize'] ?? null, $limit);
                if ($pageSize === null) {
                    $pageSize = $this->normalizeResultsPageSize($baseOptions['pageSize'] ?? null, $limit);
                }
                $fallbackSortRaw = $baseOptions['sort'] ?? null;
                $fallbackSort = is_string($fallbackSortRaw) ? $fallbackSortRaw : null;
                $sort = $this->normalizeResultsSort($options['sort'] ?? null, $fallbackSort);
                $fallbackTitle = isset($baseOptions['title'])
                    ? (string) $baseOptions['title']
                    : ($id === 'rankings' ? 'Live-Rankings' : 'Ergebnisliste');
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $placementFallbackRaw = $baseOptions['showPlacement'] ?? null;
                if (!is_bool($placementFallbackRaw)) {
                    $placementFallbackRaw = filter_var(
                        $placementFallbackRaw,
                        FILTER_VALIDATE_BOOL,
                        FILTER_NULL_ON_FAILURE
                    );
                }
                $placementFallback = is_bool($placementFallbackRaw) ? $placementFallbackRaw : false;

                $placementValueRaw = $options['showPlacement'] ?? $placementFallback;
                if (!is_bool($placementValueRaw)) {
                    $placementValueRaw = filter_var(
                        $placementValueRaw,
                        FILTER_VALIDATE_BOOL,
                        FILTER_NULL_ON_FAILURE
                    );
                }
                $placementValue = is_bool($placementValueRaw) ? $placementValueRaw : false;

                $entry['options'] = [
                    'limit' => $limit,
                    'pageSize' => $pageSize,
                    'sort' => $sort,
                    'title' => $title,
                ];
                if (array_key_exists('showPlacement', $baseOptions)) {
                    $entry['options']['showPlacement'] = $placementValue;
                }
            } elseif ($id === 'qrCodes') {
                $catalogs = [];
                $rawCatalogs = $options['catalogs'] ?? [];
                if (is_array($rawCatalogs)) {
                    foreach ($rawCatalogs as $catalogId) {
                        $normalizedId = trim((string) $catalogId);
                        if ($normalizedId === '' || in_array($normalizedId, $catalogs, true)) {
                            continue;
                        }
                        $catalogs[] = $normalizedId;
                    }
                } elseif (is_string($rawCatalogs) && $rawCatalogs !== '') {
                    $catalogs[] = $rawCatalogs;
                }
                $fallbackTitle = isset($baseOptions['title']) ? (string) $baseOptions['title'] : 'Katalog-QR-Codes';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['catalogs' => $catalogs, 'title' => $title];
            } elseif ($id === 'pointsLeader') {
                $fallbackLimit = $this->normalizePointsLeaderLimit($baseOptions['limit'] ?? null);
                if ($fallbackLimit === null) {
                    $fallbackLimit = 5;
                }
                $limit = $this->normalizePointsLeaderLimit($options['limit'] ?? null);
                if ($limit === null) {
                    $limit = $fallbackLimit;
                }
                $fallbackTitle = isset($baseOptions['title']) ? (string) $baseOptions['title'] : '';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['title' => $title, 'limit' => $limit];
            } elseif (in_array($id, ['wrongAnswers', 'infoBanner', 'rankingQr', 'media'], true)) {
                $fallbackTitle = isset($baseOptions['title']) ? (string) $baseOptions['title'] : '';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['title' => $title];
            } elseif ($options !== []) {
                $entry['options'] = $options;
            }
            $entry['enabled'] = (bool) $entry['enabled'];
            $normalized[] = $entry;
            $seen[$id] = true;
        }

        foreach ($defaults as $module) {
            if (!isset($seen[$module['id']])) {
                $normalized[] = $module;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeModuleTitle($value, string $fallback): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return $fallback;
    }

    /**
     * Normalize the configured dashboard theme.
     *
     * @param mixed $value
     */
    private function normalizeTheme($value): string
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'dark' || $normalized === 'light') {
                return $normalized;
            }
        }

        return 'light';
    }

    /**
     * @param mixed $value
     */
    private function normalizeResultsLimit($value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '0') {
                return null;
            }
            if (!is_numeric($trimmed)) {
                return null;
            }
            $value = $trimmed;
        }
        if (is_int($value)) {
            $limit = $value;
        } elseif (is_numeric($value)) {
            $limit = (int) $value;
        } else {
            return null;
        }
        if ($limit <= 0) {
            return null;
        }
        if ($limit > self::DASHBOARD_RESULTS_MAX_LIMIT) {
            $limit = self::DASHBOARD_RESULTS_MAX_LIMIT;
        }
        return $limit;
    }

    /**
     * @param mixed    $value
     * @param int|null $limit
     */
    private function normalizeResultsPageSize($value, ?int $limit): ?int
    {
        if ($limit === null || $limit <= 0) {
            return null;
        }

        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '0') {
                return null;
            }
            if (!is_numeric($trimmed)) {
                return null;
            }
            $value = $trimmed;
        }
        if (is_int($value)) {
            $pageSize = $value;
        } elseif (is_numeric($value)) {
            $pageSize = (int) $value;
        } else {
            return null;
        }
        if ($pageSize <= 0 || $pageSize > $limit) {
            return null;
        }

        return $pageSize;
    }

    /**
     * @param mixed $value
     */
    private function normalizePointsLeaderLimit($value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (!is_numeric($trimmed)) {
                return null;
            }
            $value = $trimmed;
        }
        if (is_int($value)) {
            $limit = $value;
        } elseif (is_numeric($value)) {
            $limit = (int) $value;
        } else {
            return null;
        }
        if ($limit < self::DASHBOARD_POINTS_LEADER_MIN_LIMIT) {
            return null;
        }
        if ($limit > self::DASHBOARD_POINTS_LEADER_MAX_LIMIT) {
            $limit = self::DASHBOARD_POINTS_LEADER_MAX_LIMIT;
        }

        return $limit;
    }

    /**
     * @param mixed $value
     */
    private function normalizeResultsSort($value, ?string $fallback): string
    {
        if (is_string($value) && in_array($value, self::DASHBOARD_RESULTS_SORT_OPTIONS, true)) {
            return $value;
        }

        if ($fallback !== null && $fallback !== '') {
            if ($fallback === self::DASHBOARD_RESULTS_SORT_OPTIONS[0]) {
                return $fallback;
            }
            if (in_array($fallback, self::DASHBOARD_RESULTS_SORT_OPTIONS, true)) {
                return $fallback;
            }
        }

        return self::DASHBOARD_RESULTS_SORT_OPTIONS[0];
    }

    private function sanitizeRefreshInterval(int $interval): int
    {
        if ($interval < 5) {
            return 15;
        }
        if ($interval > 300) {
            return 300;
        }
        return $interval;
    }

    /**
     * @param string $value
     * @return array<int,string>
     */
    private function extractMediaItems(string $value): array
    {
        $lines = preg_split('/\r?\n/', $value) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $items[] = $line;
        }
        return $items;
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        return $dt === false ? null : $dt;
    }

    private function isWithinWindow(?DateTimeImmutable $start, ?DateTimeImmutable $end): bool
    {
        $now = new DateTimeImmutable('now');
        if ($start !== null && $now < $start) {
            return false;
        }
        if ($end !== null && $now > $end) {
            return false;
        }
        return true;
    }
}
