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
        'competitionMode' => false,
        'teamResults' => false,
        'photoUpload' => false,
        'collectPlayerUid' => false,
        'puzzleWordEnabled' => false,
        'puzzleWord' => '',
        'puzzleFeedback' => '',
    ];

    /**
     * Validate incoming configuration data.
     *
     * @param array<string,mixed> $data
     * @return array{config: array<string,mixed>, errors: array<string,string>}
     */
    public function validate(array $data): array
    {
        $config = [];
        $errors = [];

        // pageTitle
        $title = trim((string)($data['pageTitle'] ?? self::DEFAULTS['pageTitle']));
        if ($title === '') {
            $errors['pageTitle'] = 'Page title is required';
            $title = self::DEFAULTS['pageTitle'];
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
                'competitionMode',
                'teamResults',
                'photoUpload',
                'collectPlayerUid',
                'puzzleWordEnabled',
            ] as $key
        ) {
            $config[$key] = filter_var($data[$key] ?? self::DEFAULTS[$key], FILTER_VALIDATE_BOOL);
        }

        // puzzleWord
        $config['puzzleWord'] = trim((string)($data['puzzleWord'] ?? self::DEFAULTS['puzzleWord']));
        $config['puzzleFeedback'] = trim((string)($data['puzzleFeedback'] ?? self::DEFAULTS['puzzleFeedback']));

        return ['config' => $config, 'errors' => $errors];
    }

    private function isValidColor(string $color): bool
    {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color);
    }
}
