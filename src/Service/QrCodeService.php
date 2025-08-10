<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Throwable;

class QrCodeService
{
    private const QR_SIZE_DEF = 300;
    private const QR_MARGIN_DEF = 10;
    private const LOGO_WIDTH_DEF = 80;
    private const FONT_SIZE_DEF = 20;
    /**
     * Generate a QR code with the project's default styling.
     *
     * @param array{fg?:string,bg?:string,logoText?:string} $options
     * @throws Throwable
     */
    public function generateQrCode(string $data, string $format = 'png', array $options = []): ResultInterface
    {
        $fgHex = $options['fg'] ?? '004BC8';
        $bgHex = $options['bg'] ?? 'FFFFFF';
        $logoText = $options['logoText'] ?? "QUIZ\nRACE";

        $fg = $this->parseColor($fgHex, new Color(0, 75, 200));
        $bg = $this->parseColor($bgHex, new Color(255, 255, 255));

        $font = $this->getFontFile();
        $logoPath = null;
        if ($font !== null && function_exists('imagecreatetruecolor')) {
            $logoPath = $this->createTextLogo($logoText, $font, 20, [0, 0, 0]);
        }

        $writer = strtolower($format) === 'svg' ? new SvgWriter() : new PngWriter();

        try {
            $result = (new Builder(
                writer: $writer,
                data: $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: $fg,
                backgroundColor: $bg,
                logoPath: $logoPath ?? '',
                logoResizeToWidth: $logoPath !== null ? 80 : null,
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
    public function generateCatalog(array $q): array
    {
        return $this->buildQrWithCenterLogoParam($q, [
            't' => 'https://quizrace.app/?katalog=station',
            'fg' => 'dc0000',
        ]);
    }

    /**
     * @return array{mime:string,body:string}
     */
    public function generateTeam(array $q): array
    {
        return $this->buildQrWithCenterLogoParam($q, [
            't' => 'Team 1',
            'fg' => '004bc8',
        ]);
    }

    /**
     * @param array<string,mixed> $q
     * @param array{t:string,fg:string} $defaults
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
        $text1 = (string) ($q['text1'] ?? 'QUIZ');
        $text2 = (string) ($q['text2'] ?? 'RACE');

        $rounded = $this->boolParam($q['rounded'] ?? null, true);
        $ec = $this->ecFromParam($q['ec'] ?? null);

        $roundMode = $rounded ? RoundBlockSizeMode::Margin : RoundBlockSizeMode::None;
        $qr = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $ec,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: $roundMode,
            foregroundColor: new Color($fgRgb[0], $fgRgb[1], $fgRgb[2]),
            backgroundColor: new Color($bgRgb[0], $bgRgb[1], $bgRgb[2])
        );

        $logoPath = null;
        $fontFile = $this->getFontFile();
        if ($fontFile !== null && extension_loaded('gd')) {
            $logoPath = $this->createTextLogoPng($text1, $text2, $fontFile, $fontSz, [0, 0, 0]);
        }
        $logo = $logoPath !== null ? new Logo($logoPath, $logoW) : null;

        try {
            if ($format === 'svg') {
                $body = (new SvgWriter())->write($qr, $logo)->getString();
                $mime = 'image/svg+xml';
            } else {
                $body = (new PngWriter())->write($qr, $logo)->getString();
                $mime = 'image/png';
            }
        } finally {
            if ($logoPath !== null) {
                @unlink($logoPath);
            }
        }

        return ['mime' => $mime, 'body' => $body];
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
            default => ErrorCorrectionLevel::High,
        };
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
     * Create PNG logo with two lines of centered text.
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
        $w1 = $measure($line1);
        $w2 = $measure($line2);
        $width = max($w1, $w2) + $padding * 2;
        $height = 2 * $lineHeight + $padding * 2;

        $im = imagecreatetruecolor($width, $height);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        $col = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);

        $draw = function (string $t, int $y) use ($im, $fontFile, $fontSize, $width, $col): void {
            $bb = imagettfbbox($fontSize, 0, $fontFile, $t);
            $tw = abs($bb[2] - $bb[0]);
            $x = (int) (($width - $tw) / 2);
            imagettftext($im, $fontSize, 0, $x, $y, $col, $fontFile, $t);
        };
        $y1 = $padding + $fontSize;
        $y2 = $y1 + $lineHeight;
        $draw($line1, $y1);
        $draw($line2, $y2);

        $tmp = tempnam(sys_get_temp_dir(), 'qrlogo_') . '.png';
        imagepng($im, $tmp);
        imagedestroy($im);
        return $tmp;
    }
}
