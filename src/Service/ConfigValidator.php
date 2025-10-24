<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

/**
 * Validates and normalizes configuration payloads.
 */
class ConfigValidator
{
    /**
     * Default configuration values used when a field is missing or invalid.
     *
     * @var array<string,mixed>
     */
    private const DEFAULTS = [
        'pageTitle' => 'Modernes Quiz mit UIkit',
        'backgroundColor' => '#ffffff',
        'buttonColor' => '#1e87f0',
        'startTheme' => 'light',
        'CheckAnswerButton' => 'no',
        'QRUser' => false,
        'QRRestrict' => false,
        'randomNames' => false,
        'shuffleQuestions' => true,
        'competitionMode' => false,
        'teamResults' => false,
        'photoUpload' => false,
        'collectPlayerUid' => false,
        'countdownEnabled' => false,
        'countdown' => 0,
        'puzzleWordEnabled' => false,
        'puzzleWord' => '',
        'puzzleFeedback' => '',
        'dashboardModules' => [],
        'dashboardTheme' => 'light',
        'dashboardRefreshInterval' => 15,
        'dashboardShareEnabled' => false,
        'dashboardSponsorEnabled' => false,
        'dashboardInfoText' => '',
        'dashboardMediaEmbed' => '',
        'dashboardVisibilityStart' => '',
        'dashboardVisibilityEnd' => '',
    ];

    private const DASHBOARD_ALLOWED_METRICS = ['points', 'puzzle', 'catalog', 'accuracy'];

    private const DASHBOARD_ALLOWED_LAYOUTS = ['auto', 'wide', 'full'];

    private const DASHBOARD_RESULTS_SORT_OPTIONS = ['time', 'points', 'name'];

    private const DASHBOARD_RESULTS_MAX_LIMIT = 50;

    private const DASHBOARD_MIN_REFRESH = 5;

    private const DASHBOARD_MAX_REFRESH = 300;

    /**
     * Validate incoming configuration data.
     *
     * @param array<string,mixed> $data   Configuration payload to validate
     * @param string|null        $eventName Optional event name used as fallback for the page title
     *
     * @return array{config: array<string,mixed>, errors: array<string,string>}
     */
    public function validate(array $data, ?string $eventName = null): array {
        $config = [];
        $errors = [];

        // pageTitle
        $title = trim((string)($data['pageTitle'] ?? ''));
        if ($title === '') {
            $fallback = $eventName !== null ? trim($eventName) : '';
            $title = $fallback !== '' ? $fallback : self::DEFAULTS['pageTitle'];
        }
        $config['pageTitle'] = $title;

        // backgroundColor
        $bg = (string)($data['backgroundColor'] ?? self::DEFAULTS['backgroundColor']);
        if (!$this->isValidColor($bg)) {
            $errors['backgroundColor'] = 'Invalid color value';
            $bg = self::DEFAULTS['backgroundColor'];
        }
        $config['backgroundColor'] = $bg;

        // buttonColor
        $btn = (string)($data['buttonColor'] ?? self::DEFAULTS['buttonColor']);
        if (!$this->isValidColor($btn)) {
            $errors['buttonColor'] = 'Invalid color value';
            $btn = self::DEFAULTS['buttonColor'];
        }
        $config['buttonColor'] = $btn;

        // startTheme (light/dark)
        $themeRaw = (string)($data['startTheme'] ?? self::DEFAULTS['startTheme']);
        $normalizedTheme = strtolower($themeRaw);
        if (!in_array($normalizedTheme, ['light', 'dark'], true)) {
            $errors['startTheme'] = 'Ungültige Startansicht. Bitte "hell" oder "dunkel" wählen.';
            $normalizedTheme = self::DEFAULTS['startTheme'];
        }
        $config['startTheme'] = $normalizedTheme;

        // CheckAnswerButton expects yes/no
        $chk = isset($data['CheckAnswerButton']) && $data['CheckAnswerButton'] !== 'no';
        $config['CheckAnswerButton'] = $chk ? 'yes' : 'no';

        // boolean flags
        foreach (
            [
                'QRUser',
                'QRRestrict',
                'randomNames',
                'shuffleQuestions',
                'competitionMode',
                'teamResults',
                'photoUpload',
                'collectPlayerUid',
                'countdownEnabled',
                'puzzleWordEnabled',
            ] as $key
        ) {
            $config[$key] = filter_var($data[$key] ?? self::DEFAULTS[$key], FILTER_VALIDATE_BOOL);
        }

        // countdown in seconds (non-negative integer)
        $countdownRaw = $data['countdown'] ?? self::DEFAULTS['countdown'];
        if (is_string($countdownRaw) && $countdownRaw === '') {
            $config['countdown'] = 0;
        } else {
            $countdown = filter_var(
                $countdownRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]]
            );
            if ($countdown === false) {
                $errors['countdown'] = 'Countdown must be a non-negative integer';
                $config['countdown'] = 0;
            } else {
                $config['countdown'] = (int) $countdown;
            }
        }

        // puzzleWord
        $config['puzzleWord'] = trim((string)($data['puzzleWord'] ?? self::DEFAULTS['puzzleWord']));
        $config['puzzleFeedback'] = trim((string)($data['puzzleFeedback'] ?? self::DEFAULTS['puzzleFeedback']));

        $config['dashboardModules'] = $this->normalizeDashboardModules($data['dashboardModules'] ?? self::DEFAULTS['dashboardModules']);

        $dashboardThemeRaw = (string)($data['dashboardTheme'] ?? self::DEFAULTS['dashboardTheme']);
        $normalizedDashboardTheme = strtolower(trim($dashboardThemeRaw));
        if (!in_array($normalizedDashboardTheme, ['light', 'dark'], true)) {
            $errors['dashboardTheme'] = 'Ungültiger Dashboard-Modus. Bitte "light" oder "dark" wählen.';
            $normalizedDashboardTheme = self::DEFAULTS['dashboardTheme'];
        }
        $config['dashboardTheme'] = $normalizedDashboardTheme;

        $refreshRaw = $data['dashboardRefreshInterval'] ?? self::DEFAULTS['dashboardRefreshInterval'];
        $refresh = filter_var(
            $refreshRaw,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => self::DASHBOARD_MIN_REFRESH, 'max_range' => self::DASHBOARD_MAX_REFRESH]]
        );
        if ($refresh === false) {
            $errors['dashboardRefreshInterval'] = sprintf(
                'Aktualisierungsintervall muss zwischen %d und %d Sekunden liegen.',
                self::DASHBOARD_MIN_REFRESH,
                self::DASHBOARD_MAX_REFRESH
            );
            $refresh = self::DEFAULTS['dashboardRefreshInterval'];
        }
        $config['dashboardRefreshInterval'] = (int) $refresh;

        foreach (['dashboardShareEnabled', 'dashboardSponsorEnabled'] as $flag) {
            $config[$flag] = filter_var($data[$flag] ?? self::DEFAULTS[$flag], FILTER_VALIDATE_BOOL);
        }

        $info = trim((string)($data['dashboardInfoText'] ?? self::DEFAULTS['dashboardInfoText']));
        $config['dashboardInfoText'] = ConfigService::sanitizeHtml($info);

        $mediaInput = trim((string)($data['dashboardMediaEmbed'] ?? self::DEFAULTS['dashboardMediaEmbed']));
        if ($mediaInput === '') {
            $config['dashboardMediaEmbed'] = '';
        } else {
            $lines = preg_split('/\r?\n/', $mediaInput) ?: [];
            $validLines = [];
            $mediaError = false;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (filter_var($line, FILTER_VALIDATE_URL) === false) {
                    $mediaError = true;
                    continue;
                }
                $validLines[] = $line;
            }
            if ($mediaError) {
                $errors['dashboardMediaEmbed'] = 'Ungültige URL im Medienbereich. Nur http/https-Links sind erlaubt.';
            }
            $config['dashboardMediaEmbed'] = implode("\n", $validLines);
        }

        $dates = [
            'dashboardVisibilityStart' => $data['dashboardVisibilityStart'] ?? self::DEFAULTS['dashboardVisibilityStart'],
            'dashboardVisibilityEnd' => $data['dashboardVisibilityEnd'] ?? self::DEFAULTS['dashboardVisibilityEnd'],
        ];
        $parsed = [];
        foreach ($dates as $key => $rawValue) {
            $raw = trim((string)$rawValue);
            if ($raw === '') {
                $config[$key] = '';
                $parsed[$key] = null;
                continue;
            }
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw);
            if ($dt === false) {
                $errors[$key] = 'Ungültiges Datum. Bitte Format JJJJ-MM-TT hh:mm verwenden.';
                $config[$key] = '';
                $parsed[$key] = null;
            } else {
                $config[$key] = $dt->format('Y-m-d\TH:i');
                $parsed[$key] = $dt;
            }
        }
        $visibilityStart = $parsed['dashboardVisibilityStart'] ?? null;
        $visibilityEnd = $parsed['dashboardVisibilityEnd'] ?? null;
        if ($visibilityStart instanceof DateTimeImmutable && $visibilityEnd instanceof DateTimeImmutable
            && $visibilityStart > $visibilityEnd
        ) {
            $errors['dashboardVisibilityEnd'] = 'Endzeit muss nach der Startzeit liegen.';
        }

        return ['config' => $config, 'errors' => $errors];
    }

    /**
     * @return array<int,array{id:string,enabled:bool,options?:array<string,mixed>}>
     */
    private function defaultDashboardModules(): array {
        return [
            ['id' => 'header', 'enabled' => true, 'layout' => 'full'],
            ['id' => 'pointsLeader', 'enabled' => true, 'layout' => 'wide', 'options' => ['title' => 'Platzierungen']],
            [
                'id' => 'rankings',
                'enabled' => true,
                'layout' => 'wide',
                'options' => ['metrics' => self::DASHBOARD_ALLOWED_METRICS, 'title' => 'Live-Rankings'],
            ],
            [
                'id' => 'results',
                'enabled' => true,
                'layout' => 'full',
                'options' => ['limit' => null, 'sort' => 'time', 'title' => 'Ergebnisliste'],
            ],
            ['id' => 'wrongAnswers', 'enabled' => false, 'layout' => 'auto', 'options' => ['title' => 'Falsch beantwortete Fragen']],
            ['id' => 'infoBanner', 'enabled' => false, 'layout' => 'auto', 'options' => ['title' => 'Hinweise']],
            ['id' => 'rankingQr', 'enabled' => false, 'layout' => 'auto', 'options' => ['title' => 'Ranking-QR']],
            ['id' => 'qrCodes', 'enabled' => false, 'layout' => 'auto', 'options' => ['catalogs' => [], 'title' => 'Katalog-QR-Codes']],
            ['id' => 'media', 'enabled' => false, 'layout' => 'auto', 'options' => ['title' => 'Highlights']],
        ];
    }

    /**
     * Normalize the dashboard modules configuration.
     *
     * @param mixed $value
     * @return array<int,array{id:string,enabled:bool,options?:array<string,mixed>}>
     */
    private function normalizeDashboardModules($value): array {
        $modules = [];
        if (is_string($value) && $value !== '') {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $modules = $decoded;
                }
            } catch (JsonException $e) {
                $modules = [];
            }
        } elseif (is_array($value)) {
            $modules = $value;
        }

        $defaults = [];
        foreach ($this->defaultDashboardModules() as $module) {
            $defaults[$module['id']] = $module;
        }

        $normalized = [];
        $seen = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }
            $id = isset($module['id']) ? (string)$module['id'] : '';
            if ($id === '' || !isset($defaults[$id]) || isset($seen[$id])) {
                continue;
            }
            $base = $defaults[$id];
            $enabled = filter_var($module['enabled'] ?? $base['enabled'], FILTER_VALIDATE_BOOL);
            $baseLayout = isset($base['layout']) ? (string)$base['layout'] : 'auto';
            $layout = isset($module['layout']) ? (string)$module['layout'] : $baseLayout;
            if (!in_array($layout, self::DASHBOARD_ALLOWED_LAYOUTS, true)) {
                $layout = $baseLayout;
            }

            $baseOptions = isset($base['options']) && is_array($base['options']) ? $base['options'] : [];
            $options = isset($module['options']) && is_array($module['options']) ? $module['options'] : [];

            $entry = ['id' => $id, 'enabled' => (bool)$enabled, 'layout' => $layout];
            if ($id === 'rankings') {
                $metrics = [];
                if (isset($options['metrics']) && is_array($options['metrics'])) {
                    foreach ($options['metrics'] as $metric) {
                        $metricId = (string)$metric;
                        if (in_array($metricId, self::DASHBOARD_ALLOWED_METRICS, true) && !in_array($metricId, $metrics, true)) {
                            $metrics[] = $metricId;
                        }
                    }
                }
                if ($metrics === []) {
                    $metrics = self::DASHBOARD_ALLOWED_METRICS;
                }
                $fallbackTitle = isset($baseOptions['title']) ? (string)$baseOptions['title'] : 'Live-Rankings';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['metrics' => $metrics, 'title' => $title];
            } elseif ($id === 'results') {
                $limit = $this->normalizeResultsLimit($options['limit'] ?? null);
                if ($limit === null) {
                    $limit = $this->normalizeResultsLimit($baseOptions['limit'] ?? null);
                }
                $sort = isset($options['sort']) ? (string)$options['sort'] : '';
                if (!in_array($sort, self::DASHBOARD_RESULTS_SORT_OPTIONS, true)) {
                    $sort = isset($baseOptions['sort']) ? (string)$baseOptions['sort'] : 'time';
                    if (!in_array($sort, self::DASHBOARD_RESULTS_SORT_OPTIONS, true)) {
                        $sort = 'time';
                    }
                }
                $fallbackTitle = isset($baseOptions['title']) ? (string)$baseOptions['title'] : 'Ergebnisliste';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = [
                    'limit' => $limit,
                    'sort' => $sort,
                    'title' => $title,
                ];
            } elseif ($id === 'qrCodes') {
                $catalogs = [];
                $rawCatalogs = $options['catalogs'] ?? [];
                if (is_array($rawCatalogs)) {
                    foreach ($rawCatalogs as $catalogId) {
                        $normalizedId = trim((string)$catalogId);
                        if ($normalizedId === '') {
                            continue;
                        }
                        if (!in_array($normalizedId, $catalogs, true)) {
                            $catalogs[] = $normalizedId;
                        }
                    }
                } elseif (is_string($rawCatalogs) && $rawCatalogs !== '') {
                    $catalogs[] = $rawCatalogs;
                }
                $fallbackTitle = isset($baseOptions['title']) ? (string)$baseOptions['title'] : 'Katalog-QR-Codes';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['catalogs' => $catalogs, 'title' => $title];
            } elseif (in_array($id, ['pointsLeader', 'wrongAnswers', 'infoBanner', 'rankingQr', 'media'], true)) {
                $fallbackTitle = isset($baseOptions['title']) ? (string)$baseOptions['title'] : '';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['title' => $title];
            } elseif ($options !== []) {
                $entry['options'] = $options;
            }
            $normalized[] = $entry;
            $seen[$id] = true;
        }

        foreach ($defaults as $id => $module) {
            if (!isset($seen[$id])) {
                $normalized[] = $module;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeModuleTitle($value, string $fallback): string {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return $fallback;
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
            $limit = (int)$value;
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

    private function isValidColor(string $color): bool {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color);
    }
}
