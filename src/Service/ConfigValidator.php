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
        'dashboardFixedHeight' => '',
        'dashboardShareEnabled' => false,
        'dashboardSponsorEnabled' => false,
        'dashboardInfoText' => '',
        'dashboardMediaEmbed' => '',
        'dashboardVisibilityStart' => '',
        'dashboardVisibilityEnd' => '',
    ];


    private const DASHBOARD_ALLOWED_LAYOUTS = ['auto', 'wide', 'full'];

    private const DASHBOARD_RESULTS_SORT_OPTIONS = ['time', 'points', 'name'];

    private const DASHBOARD_RESULTS_MAX_LIMIT = 50;

    private const DASHBOARD_POINTS_LEADER_MIN_LIMIT = 1;

    private const DASHBOARD_POINTS_LEADER_MAX_LIMIT = 10;

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

        $heightRaw = $data['dashboardFixedHeight'] ?? self::DEFAULTS['dashboardFixedHeight'];
        $heightNormalized = $this->normalizeDashboardFixedHeight($heightRaw);
        if ($heightNormalized === null) {
            $config['dashboardFixedHeight'] = '';
            $trimmed = is_string($heightRaw) || is_numeric($heightRaw)
                ? trim((string) $heightRaw)
                : '';
            if ($trimmed !== '') {
                $errors['dashboardFixedHeight'] = 'Ungültige Höhe. Bitte px, vh oder rem verwenden.';
            } elseif (!is_string($heightRaw) && !is_numeric($heightRaw)) {
                $errors['dashboardFixedHeight'] = 'Ungültige Höhe. Bitte px, vh oder rem verwenden.';
            }
        } else {
            $config['dashboardFixedHeight'] = $heightNormalized;
        }

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
     * Normalize a dashboard fixed height value.
     */
    private function normalizeDashboardFixedHeight(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }

        if (is_numeric($value)) {
            $raw = (string) $value;
        } elseif (is_string($value)) {
            $raw = trim($value);
        } else {
            return null;
        }

        if ($raw === '') {
            return '';
        }

        $normalized = strtolower(str_replace(' ', '', $raw));
        $normalized = str_replace(',', '.', $normalized);

        if (preg_match('/^(?<amount>\d+(?:\.\d+)?)(?<unit>px|vh|vw|rem)?$/', $normalized, $matches) !== 1) {
            return null;
        }

        $amount = $matches['amount'];
        if (str_contains($amount, '.')) {
            $amount = rtrim(rtrim($amount, '0'), '.');
        }

        if ($amount === '') {
            return null;
        }

        $unit = $matches['unit'] ?? '';
        if ($unit === '') {
            $unit = 'px';
        }

        return $amount . $unit;
    }

    /**
     * @return array<int,array{id:string,enabled:bool,layout:string,options?:array<string,mixed>}>
     */
    private function defaultDashboardModules(): array {
        return [
            ['id' => 'header', 'enabled' => true, 'layout' => 'full'],
            ['id' => 'pointsLeader', 'enabled' => true, 'layout' => 'wide', 'options' => ['title' => 'Platzierungen', 'limit' => 5]],
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
                'options' => ['limit' => null, 'pageSize' => null, 'sort' => 'time', 'title' => 'Ergebnisliste'],
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
     * @return array<int,array{id:string,enabled:bool,layout:string,options?:array<string,mixed>}>
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
            $baseLayout = (string) $base['layout'];
            $layout = isset($module['layout']) ? (string)$module['layout'] : $baseLayout;
            if (!in_array($layout, self::DASHBOARD_ALLOWED_LAYOUTS, true)) {
                $layout = $baseLayout;
            }

            $baseOptionsRaw = $base['options'] ?? null;
            $baseOptions = is_array($baseOptionsRaw) ? $baseOptionsRaw : [];
            $optionsRaw = $module['options'] ?? null;
            $options = is_array($optionsRaw) ? $optionsRaw : [];

            $entry = ['id' => $id, 'enabled' => (bool)$enabled, 'layout' => $layout];
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
                    ? (string)$baseOptions['title']
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
                $optionsData = [
                    'limit' => $limit,
                    'pageSize' => $pageSize,
                    'sort' => $sort,
                    'title' => $title,
                ];
                if ($id === 'rankings') {
                    $optionsData['showPlacement'] = $placementValue;
                }
                $entry['options'] = $optionsData;
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
            } elseif ($id === 'pointsLeader') {
                $fallbackLimit = $this->normalizePointsLeaderLimit($baseOptions['limit'] ?? null);
                if ($fallbackLimit === null) {
                    $fallbackLimit = 5;
                }
                $limit = $this->normalizePointsLeaderLimit($options['limit'] ?? null);
                if ($limit === null) {
                    $limit = $fallbackLimit;
                }
                $fallbackTitle = isset($baseOptions['title']) ? (string)$baseOptions['title'] : '';
                $title = $this->normalizeModuleTitle($options['title'] ?? null, $fallbackTitle);
                $entry['options'] = ['title' => $title, 'limit' => $limit];
            } elseif (in_array($id, ['wrongAnswers', 'infoBanner', 'rankingQr', 'media'], true)) {
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
            $pageSize = (int)$value;
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

    private function isValidColor(string $color): bool {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color);
    }
}
