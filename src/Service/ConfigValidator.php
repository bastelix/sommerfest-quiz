<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use JsonException;

/**
 * Validates and normalizes configuration payloads.
 */
class ConfigValidator
{
    /**
     * Default dashboard module configuration.
     *
     * @var list<array{id:string,enabled:bool}>
     */
    public const DEFAULT_DASHBOARD_MODULES = [
        ['id' => 'rankings', 'enabled' => true],
        ['id' => 'results', 'enabled' => true],
        ['id' => 'questions', 'enabled' => false],
        ['id' => 'info', 'enabled' => false],
        ['id' => 'media', 'enabled' => false],
    ];

    /**
     * Default configuration values used when a field is missing or invalid.
     *
     * @var array<string,mixed>
     */
    private const DEFAULTS = [
        'pageTitle' => 'Modernes Quiz mit UIkit',
        'backgroundColor' => '#ffffff',
        'buttonColor' => '#1e87f0',
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
        'dashboardEnabled' => false,
        'dashboardRefreshInterval' => 15,
        'dashboardRankingLimit' => 5,
        'dashboardInfo' => '',
        'dashboardMediaUrl' => '',
    ];

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
                'dashboardEnabled',
            ] as $key
        ) {
            $config[$key] = filter_var($data[$key] ?? self::DEFAULTS[$key], FILTER_VALIDATE_BOOL);
        }

        // countdown in seconds (non-negative integer)
        $countdownRaw = $data['countdown'] ?? self::DEFAULTS['countdown'];
        if ($countdownRaw === null || $countdownRaw === '') {
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

        // dashboard refresh interval (5-120 seconds)
        $refreshRaw = $data['dashboardRefreshInterval'] ?? self::DEFAULTS['dashboardRefreshInterval'];
        $refresh = filter_var(
            $refreshRaw,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 5, 'max_range' => 120]]
        );
        if ($refresh === false) {
            $errors['dashboardRefreshInterval'] = 'Refresh interval must be between 5 and 120 seconds';
            $refresh = self::DEFAULTS['dashboardRefreshInterval'];
        }
        $config['dashboardRefreshInterval'] = (int) $refresh;

        // dashboard ranking limit (1-20)
        $rankingRaw = $data['dashboardRankingLimit'] ?? self::DEFAULTS['dashboardRankingLimit'];
        $ranking = filter_var(
            $rankingRaw,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 20]]
        );
        if ($ranking === false) {
            $errors['dashboardRankingLimit'] = 'Ranking limit must be between 1 and 20';
            $ranking = self::DEFAULTS['dashboardRankingLimit'];
        }
        $config['dashboardRankingLimit'] = (int) $ranking;

        // dashboard info text
        $info = trim((string)($data['dashboardInfo'] ?? self::DEFAULTS['dashboardInfo']));
        $config['dashboardInfo'] = ConfigService::sanitizeHtml($info);

        // dashboard media url (allow http/https only)
        $mediaUrl = trim((string)($data['dashboardMediaUrl'] ?? self::DEFAULTS['dashboardMediaUrl']));
        if ($mediaUrl !== '' && !preg_match('/^https?:\/\//i', $mediaUrl)) {
            $errors['dashboardMediaUrl'] = 'Media URL must start with http:// or https://';
            $mediaUrl = '';
        }
        $config['dashboardMediaUrl'] = $mediaUrl;

        // dashboard modules configuration
        $modules = $this->normalizeDashboardModules($data['dashboardModules'] ?? null);
        try {
            $config['dashboardModules'] = json_encode($modules, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Unable to encode dashboard modules', 0, $e);
        }

        return ['config' => $config, 'errors' => $errors];
    }

    private function isValidColor(string $color): bool {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color);
    }

    /**
     * Normalize dashboard module configuration input.
     *
     * @param mixed $value
     * @return list<array{id:string,enabled:bool}>
     */
    private function normalizeDashboardModules($value): array {
        $defaults = self::DEFAULT_DASHBOARD_MODULES;
        $input = [];
        if (is_string($value) && $value !== '') {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $input = $decoded;
                }
            } catch (JsonException $e) {
                $input = [];
            }
        } elseif (is_array($value)) {
            $input = $value;
        }

        $known = [];
        foreach ($defaults as $module) {
            $id = (string) ($module['id'] ?? '');
            if ($id !== '') {
                $known[$id] = (bool) ($module['enabled'] ?? false);
            }
        }

        $result = [];
        $seen = [];
        foreach ($input as $entry) {
            if (is_array($entry)) {
                $id = isset($entry['id']) ? (string) $entry['id'] : '';
                if ($id === '' || !array_key_exists($id, $known) || isset($seen[$id])) {
                    continue;
                }
                $enabled = array_key_exists('enabled', $entry)
                    ? filter_var($entry['enabled'], FILTER_VALIDATE_BOOL)
                    : $known[$id];
                $result[] = ['id' => $id, 'enabled' => (bool) $enabled];
                $seen[$id] = true;
            } elseif (is_string($entry)) {
                $id = $entry;
                if ($id === '' || !array_key_exists($id, $known) || isset($seen[$id])) {
                    continue;
                }
                $result[] = ['id' => $id, 'enabled' => $known[$id]];
                $seen[$id] = true;
            }
        }

        foreach ($defaults as $module) {
            $id = (string) ($module['id'] ?? '');
            if ($id !== '' && !isset($seen[$id])) {
                $result[] = ['id' => $id, 'enabled' => (bool) ($module['enabled'] ?? false)];
            }
        }

        return $result;
    }
}
