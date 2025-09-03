<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Throwable;

class QrCodeService
{
    private const QR_SIZE_DEF = 360;
    private const QR_MARGIN_DEF = 20;
    private const LOGO_WIDTH_DEF = 60;
    private const FONT_SIZE_DEF = 20;
    private const FALLBACK_LOGO_PNG_BASE64 =
        'iVBORw0KGgoAAAANSUhEUgAAAFAAAABQCAIAAAABc2X6AAABrUlEQVR4nO3aL4sCQRzG8Tk9DBbB'
        . 'aLAKikUtMoIDk4yC78WXo8V3YNhgcMMaJyyKJpNRGIOwoLBz4UCEA0XYuT/PPZ82MsjvC79di2/O'
        . 'OfGf5H56gO/GYHQMRsdgdAxGx2B0DEbHYHQMRsdgdAxGx2B0DEbH4C8mk0m73e52u51OZzabfX5Y'
        . 'LBaVUv1+v9VqhWHoechMuYeCIJBSWmudc9ZaKWUYhs65Uqn0eSGO42az+fhLfpUnwVrr1Wp1O0ZR'
        . 'NBgM3F1wmqblctnbeNl7ElypVJIkuR2TJKlWq+4uOAiC0Wjkbbzsvb+6/+fzWQhxuVyUUtfrdbfb'
        . 'bTYbP0+bF09eWo1GwxhzOxpj6vW6EKJQKCyXyyiKxuPxdDr1OmLGHi/AYrGQUp5OJ+ectbbX683n'
        . 'c3e30saY4XDodwsz9WSltdaHw0Frnc/nt9utEGK/399fqNVqcRynaZrL/Y2f9Df3yl8ejsfjer1W'
        . 'Snmbx7vXggH8jT3MEIPRMRgdg9ExGB2D0TEYHYPRMRgdg9ExGB2D0TEYHYPRMRgdg9ExGB2D0TEY'
        . 'HYPRMRgdg9F9AHRS1dmj7GtGAAAAAElFTkSuQmCC';

    /**
     * Generate a QR code with the project's default styling.
     *
     * @param array{fg?:string,bg?:string,logoText?:string} $options
     * @throws Throwable
     */
    public function generateQrCode(string $data, string $format = 'svg', array $options = []): ResultInterface
    {
        $fgHex = $options['fg'] ?? '2E2E2E';
        $bgHex = $options['bg'] ?? 'F9F9F9';
        $logoText = $options['logoText'] ?? "QUIZ\nRACE";

        $fg = $this->parseColor($fgHex, new Color(46, 46, 46));
        $bg = $this->parseColor($bgHex, new Color(249, 249, 249));

        $font = $this->getFontFile();
        $logoPath = null;
        if ($font !== null && extension_loaded('gd')) {
            $logoPath = $this->createTextLogo($logoText, $font, self::FONT_SIZE_DEF, [0, 0, 0]);
        } else {
            $tmp = tempnam(sys_get_temp_dir(), 'qrlogo_fallback');
            if ($tmp !== false) {
                $data = base64_decode(self::FALLBACK_LOGO_PNG_BASE64, true);
                file_put_contents($tmp, $data);
                $logoPath = $tmp;
            }
        }

        $format = strtolower($format);
        if ($format === 'png' && !extension_loaded('gd')) {
            $format = 'svg';
        }

        $writer = $format === 'svg' ? new SvgWriter() : new PngWriter();
        $punchout = $logoPath !== null && !($writer instanceof SvgWriter);

        try {
            $result = (new Builder(
                writer: $writer,
                data: $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: self::QR_SIZE_DEF,
                margin: self::QR_MARGIN_DEF,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: $fg,
                backgroundColor: $bg,
                logoPath: $logoPath ?? '',
                logoResizeToWidth: $logoPath !== null ? self::LOGO_WIDTH_DEF : null,
                logoPunchoutBackground: $punchout,
            ))->build();
        } finally {
            if ($logoPath !== null && file_exists($logoPath)) {
                @unlink($logoPath);
            }
        }

        return $result;
    }

    /**
     * Create a transparent PNG logo with multiline text.
     * Returns the path to the temporary file.
     *
     * @param array{0:int,1:int,2:int} $color
     */
    public function createTextLogo(string $text, string $fontFile, int $fontSize, array $color): string
    {
        $lines = explode("\n", $text);
        $lineHeights = [];
        $width = 0;
        $height = 0;
        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $fontFile, $line);
            if ($box !== false) {
                $w = $box[2] - $box[0];
                $h = $box[1] - $box[7];
                $width = max($width, $w);
                $lineHeights[] = $h;
                $height += $h;
            }
        }
        $img = imagecreatetruecolor((int)$width, (int)$height);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        $textColor = imagecolorallocate($img, $color[0], $color[1], $color[2]);
        $y = 0;
        foreach ($lines as $index => $line) {
            $h = $lineHeights[$index];
            $box = imagettfbbox($fontSize, 0, $fontFile, $line);
            $lineWidth = $box[2] - $box[0];
            $x = (int)(($width - $lineWidth) / 2);
            $y += $h;
            imagettftext($img, $fontSize, 0, $x, $y, $textColor, $fontFile, $line);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'qrlogo');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temp file');
        }
        imagepng($img, $tmp);
        imagedestroy($img);
        return $tmp;
    }

    private function parseColor(string $hex, Color $default): Color
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return new Color($r, $g, $b);
        }
        return $default;
    }

    private function getFontFile(): ?string
    {
        $candidates = [
            // Prefer bundled font to ensure consistent rendering
            __DIR__ . '/../../resources/fonts/NotoSans-Bold.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ];
        foreach ($candidates as $file) {
            if (is_readable($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * @return array{mime:string,body:string}
     */
    public function generateCatalog(array $q, array $cfg = []): array
    {
        $defaults = [
            't' => 'https://quizrace.app/?katalog=station',
            'fg' => ltrim((string)($cfg['qrColorCatalog'] ?? 'dc0000'), '#'),
        ];
        $defaults = $this->mergeDesignDefaults($defaults, $cfg);
        return $this->buildQrWithCenterLogoParam($q, $defaults);
    }

    /**
     * @return array{mime:string,body:string}
     */
    public function generateTeam(array $q, array $cfg = []): array
    {
        $defaults = [
            't' => 'Team 1',
            'fg' => ltrim((string)($cfg['qrColorTeam'] ?? '004bc8'), '#'),
        ];
        $defaults = $this->mergeDesignDefaults($defaults, $cfg);
        return $this->buildQrWithCenterLogoParam($q, $defaults);
    }

    /**
     * @return array{mime:string,body:string}
     */
    public function generateEvent(array $q, array $cfg = []): array
    {
        $defaults = [
            't' => 'https://quizrace.app/?event=station',
            'fg' => ltrim((string)($cfg['qrColorEvent'] ?? '00a65a'), '#'),
        ];
        $defaults = $this->mergeDesignDefaults($defaults, $cfg);
        return $this->buildQrWithCenterLogoParam($q, $defaults);
    }

    /**
     * @param array<string,mixed> $q
     * @param array{
     *     t:string,
     *     fg:string,
     *     text1?:string,
     *     text2?:string,
     *     round_mode?:string,
     *     logo_punchout?:bool,
     *     logo_path?:string,
     *     label_text?:string
     * } $defaults
     * @return array{mime:string,body:string}
     */
    private function buildQrWithCenterLogoParam(array $q, array $defaults): array
    {
        $data = (string) ($q['t'] ?? $defaults['t']);
        $format = strtolower((string) ($q['format'] ?? 'png'));
        if (!in_array($format, ['png', 'svg'], true)) {
            $format = 'png';
        }

        $size = $this->clampInt($q['size'] ?? null, 64, 2048, self::QR_SIZE_DEF);
        $margin = $this->clampInt($q['margin'] ?? null, 0, 40, self::QR_MARGIN_DEF);

        $fgRgb = $this->parseHex((string) ($q['fg'] ?? $defaults['fg']));
        $bgRgb = $this->parseHex((string) ($q['bg'] ?? 'ffffff'), 'ffffff');

        $logoW = $this->clampInt($q['logo_width'] ?? null, 20, 200, self::LOGO_WIDTH_DEF);
        $fontSz = $this->clampInt($q['font_size'] ?? null, 8, 48, self::FONT_SIZE_DEF);
        $text1 = (string) ($q['text1'] ?? ($defaults['text1'] ?? 'QUIZ'));
        $text2 = (string) ($q['text2'] ?? ($defaults['text2'] ?? 'RACE'));
        $labelText = (string) ($q['label_text'] ?? ($defaults['label_text'] ?? ''));

        $rounded = $this->boolParam($q['rounded'] ?? null, true);
        $roundModeParam = $q['round_mode'] ?? ($defaults['round_mode'] ?? null);
        $logoPunchout = $this->boolParam($q['logo_punchout'] ?? ($defaults['logo_punchout'] ?? null), true);
        $ec = $this->ecFromParam($q['ec'] ?? null);

        $roundMode = $this->roundModeFromParam($roundModeParam, $rounded);

        $logoPath = null;
        $logoParam = (string) ($q['logo_path'] ?? ($defaults['logo_path'] ?? ''));
        if ($logoParam !== '') {
            $p = __DIR__ . '/../../data' . (str_starts_with($logoParam, '/') ? $logoParam : '/' . $logoParam);
            if (is_readable($p)) {
                $logoPath = $p;
            }
        }
        if ($logoPath === null) {
            $fontFile = $this->getFontFile();
            if ($fontFile !== null && extension_loaded('gd')) {
                $logoPath = $this->createTextLogoPng($text1, $text2, $fontFile, $fontSz, [0, 0, 0]);
            }
        }

        $writer = $format === 'svg' ? new SvgWriter() : new PngWriter();
        $punchout = $logoPath !== null && !($writer instanceof SvgWriter) ? $logoPunchout : false;

        try {
            $builder = $labelText !== ''
                ? new Builder(
                    writer: $writer,
                    data: $data,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: $ec,
                    size: $size,
                    margin: $margin,
                    roundBlockSizeMode: $roundMode,
                    foregroundColor: new Color($fgRgb[0], $fgRgb[1], $fgRgb[2]),
                    backgroundColor: new Color($bgRgb[0], $bgRgb[1], $bgRgb[2]),
                    logoPath: $logoPath ?? '',
                    logoResizeToWidth: $logoPath !== null ? $logoW : null,
                    logoPunchoutBackground: $punchout,
                    labelText: $labelText,
                    labelFont: new OpenSans(self::FONT_SIZE_DEF),
                    labelAlignment: LabelAlignment::Center,
                )
                : new Builder(
                    writer: $writer,
                    data: $data,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: $ec,
                    size: $size,
                    margin: $margin,
                    roundBlockSizeMode: $roundMode,
                    foregroundColor: new Color($fgRgb[0], $fgRgb[1], $fgRgb[2]),
                    backgroundColor: new Color($bgRgb[0], $bgRgb[1], $bgRgb[2]),
                    logoPath: $logoPath ?? '',
                    logoResizeToWidth: $logoPath !== null ? $logoW : null,
                    logoPunchoutBackground: $punchout,
                );

            $result = $builder->build();
        } finally {
            if ($logoPath !== null) {
                @unlink($logoPath);
            }
        }

        return [
            'mime' => $result->getMimeType(),
            'body' => $result->getString(),
        ];
    }

    /**
     * Merge stored design configuration into default parameters.
     *
     * @param array<string,mixed> $defaults
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function mergeDesignDefaults(array $defaults, array $cfg): array
    {
        if (($cfg['qrLabelLine1'] ?? '') !== '') {
            $defaults['text1'] = (string) $cfg['qrLabelLine1'];
        }
        if (($cfg['qrLabelLine2'] ?? '') !== '') {
            $defaults['text2'] = (string) $cfg['qrLabelLine2'];
        }
        if (($cfg['qrLabelBottom'] ?? '') !== '') {
            $defaults['label_text'] = (string) $cfg['qrLabelBottom'];
        }
        if (($cfg['qrLogoPath'] ?? '') !== '') {
            $defaults['logo_path'] = (string) $cfg['qrLogoPath'];
        }
        if (($cfg['qrRoundMode'] ?? '') !== '') {
            $defaults['round_mode'] = (string) $cfg['qrRoundMode'];
        }
        if (array_key_exists('qrLogoPunchout', $cfg) && $cfg['qrLogoPunchout'] !== null) {
            $defaults['logo_punchout'] = $cfg['qrLogoPunchout'] ? '1' : '0';
        }
        if (array_key_exists('qrRounded', $cfg) && $cfg['qrRounded'] !== null) {
            $defaults['rounded'] = $cfg['qrRounded'] ? '1' : '0';
        }
        return $defaults;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function parseHex(string $hex, string $fallback = '000000'): array
    {
        $h = preg_replace('/[^0-9a-f]/i', '', $hex);
        if ($h === '') {
            $h = $fallback;
        }
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        $h = str_pad(substr($h, 0, 6), 6, '0');
        return [
            hexdec(substr($h, 0, 2)),
            hexdec(substr($h, 2, 2)),
            hexdec(substr($h, 4, 2)),
        ];
    }

    private function boolParam(mixed $v, bool $def = true): bool
    {
        if ($v === null) {
            return $def;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    private function ecFromParam(?string $v): ErrorCorrectionLevel
    {
        return match (strtolower((string) $v)) {
            'low' => ErrorCorrectionLevel::Low,
            'medium' => ErrorCorrectionLevel::Medium,
            'quartile' => ErrorCorrectionLevel::Quartile,
            default => ErrorCorrectionLevel::Medium,
        };
    }

    private function roundModeFromParam(mixed $v, bool $rounded): RoundBlockSizeMode
    {
        if (is_string($v)) {
            $mode = RoundBlockSizeMode::tryFrom(strtolower($v));
            if ($mode !== null) {
                return $mode;
            }
        }

        return $rounded ? RoundBlockSizeMode::Margin : RoundBlockSizeMode::None;
    }

    private function clampInt(mixed $v, int $min, int $max, int $def): int
    {
        $i = filter_var($v, FILTER_VALIDATE_INT);
        if ($i === false) {
            $i = $def;
        }
        return max($min, min($max, $i));
    }

    /**
     * Create a PNG logo with one or two lines of centered text
     * and a bordered background.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function createTextLogoPng(
        string $line1,
        string $line2,
        string $fontFile,
        int $fontSize,
        array $rgb
    ): string {
        $padding = 10;
        $lineHeight = $fontSize + 6;
        $measure = function (string $t) use ($fontFile, $fontSize): int {
            $bb = imagettfbbox($fontSize, 0, $fontFile, $t);
            return abs($bb[2] - $bb[0]);
        };

        $lines = array_values(array_filter([$line1, $line2], fn ($t) => $t !== ''));
        if ($lines === []) {
            $lines = [''];
        }

        $width = $padding * 2;
        foreach ($lines as $t) {
            $width = max($width, $measure($t) + $padding * 2);
        }
        $height = count($lines) * $lineHeight + $padding * 2;

        $im = imagecreatetruecolor($width, $height);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

        $borderCol = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        $bgCol = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $bgCol);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderCol);

        $draw = function (string $t, int $y) use ($im, $fontFile, $fontSize, $width, $borderCol): void {
            $bb = imagettfbbox($fontSize, 0, $fontFile, $t);
            $tw = abs($bb[2] - $bb[0]);
            $x = (int) (($width - $tw) / 2);
            imagettftext($im, $fontSize, 0, $x, $y, $borderCol, $fontFile, $t);
        };

        $y = $padding + $fontSize;
        foreach ($lines as $line) {
            $draw($line, $y);
            $y += $lineHeight;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'qrlogo_') . '.png';
        imagepng($im, $tmp);
        imagedestroy($im);
        return $tmp;
    }
}
