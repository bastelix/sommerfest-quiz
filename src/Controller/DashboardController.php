<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\EventService;
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
        $variant = $variantParam === 'sponsor' ? 'sponsor' : 'public';

        $event = $this->events->getBySlug($slug);
        if ($event === null) {
            return $response->withStatus(404);
        }

        $uid = (string) $event['uid'];
        $matchedVariant = $this->config->verifyDashboardToken($uid, $token, $variant);
        if ($matchedVariant === null) {
            return $response->withStatus(403);
        }

        $cfg = $this->config->getConfigForEvent($uid);
        if (($matchedVariant === 'public' && empty($cfg['dashboardShareEnabled']))
            || ($matchedVariant === 'sponsor' && empty($cfg['dashboardSponsorEnabled']))
        ) {
            return $response->withStatus(403);
        }

        $modules = $this->extractModules($cfg['dashboardModules'] ?? []);
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
                'active' => $isActive,
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
            ['id' => 'header', 'enabled' => true],
            ['id' => 'rankings', 'enabled' => true, 'options' => ['metrics' => ['points', 'puzzle', 'catalog']]],
            ['id' => 'results', 'enabled' => true],
            ['id' => 'wrongAnswers', 'enabled' => false],
            ['id' => 'infoBanner', 'enabled' => false],
            ['id' => 'qrCodes', 'enabled' => false, 'options' => ['catalogs' => []]],
            ['id' => 'media', 'enabled' => false],
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
            $entry = ['id' => $id, 'enabled' => !empty($module['enabled'])];
            if ($id === 'rankings') {
                $metrics = [];
                $options = isset($module['options']) && is_array($module['options']) ? $module['options'] : [];
                if (isset($options['metrics']) && is_array($options['metrics'])) {
                    foreach ($options['metrics'] as $metric) {
                        $metricId = (string) $metric;
                        if (in_array($metricId, ['points', 'puzzle', 'catalog'], true) && !in_array($metricId, $metrics, true)) {
                            $metrics[] = $metricId;
                        }
                    }
                }
                if ($metrics === []) {
                    $metrics = $base['options']['metrics'];
                }
                $entry['options'] = ['metrics' => $metrics];
            } elseif ($id === 'qrCodes') {
                $catalogs = [];
                $options = isset($module['options']) && is_array($module['options']) ? $module['options'] : [];
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
                $entry['options'] = ['catalogs' => $catalogs];
            } elseif (isset($module['options']) && is_array($module['options']) && $module['options'] !== []) {
                $entry['options'] = $module['options'];
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
