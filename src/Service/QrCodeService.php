<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Throwable;

class QrCodeService
{
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
}
