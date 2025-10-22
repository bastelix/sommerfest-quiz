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
        'dashboardRefreshInterval' => 15,
        'dashboardShareEnabled' => false,
        'dashboardSponsorEnabled' => false,
        'dashboardInfoText' => '',
        'dashboardMediaEmbed' => '',
        'dashboardVisibilityStart' => '',
        'dashboardVisibilityEnd' => '',
    ];

    private const DASHBOARD_ALLOWED_METRICS = ['points', 'puzzle', 'catalog'];

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
            ['id' => 'header', 'enabled' => true],
            ['id' => 'rankings', 'enabled' => true, 'options' => ['metrics' => self::DASHBOARD_ALLOWED_METRICS]],
            ['id' => 'results', 'enabled' => true],
            ['id' => 'wrongAnswers', 'enabled' => false],
            ['id' => 'infoBanner', 'enabled' => false],
            ['id' => 'media', 'enabled' => false],
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
            $entry = ['id' => $id, 'enabled' => (bool)$enabled];
            if ($id === 'rankings') {
                $metrics = [];
                $options = [];
                if (isset($module['options']) && is_array($module['options'])) {
                    $options = $module['options'];
                }
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
                $entry['options'] = ['metrics' => $metrics];
            } elseif (isset($module['options']) && is_array($module['options']) && $module['options'] !== []) {
                $entry['options'] = $module['options'];
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

    private function isValidColor(string $color): bool {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color);
    }
}
