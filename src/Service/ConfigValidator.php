<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

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

        return ['config' => $config, 'errors' => $errors];
    }

    private function isValidColor(string $color): bool {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color);
    }
}
