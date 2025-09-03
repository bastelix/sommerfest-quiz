<?php

declare(strict_types=1);

namespace App\Service;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;

use function base64_encode;
use function file_exists;
use function file_get_contents;
use function imagecolorallocate;
use function imagecolorallocatealpha;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagefilledrectangle;
use function imagerectangle;
use function imagecopyresampled;
use function imagecreatetruecolor;
use function imagefill;
use function imagepng;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function imagettfbbox;
use function imagettftext;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class QrCodeService
{
    private const QR_SIZE_DEF = 360;
    private const QR_MARGIN_DEF = 20;
    private const LOGO_WIDTH_DEF = 120;
    private const FONT_SIZE_DEF = 20;

    /**
     * Generate a QR code with the project's default styling.
     *
     * @param array{fg?:string,bg?:string,logoText?:string} $options
     * @return array{mime:string,body:string}
     */
    public function generateQrCode(string $data, string $format = 'svg', array $options = []): array
    {
        $fg = $this->parseHex($options['fg'] ?? '2E2E2E', '2E2E2E');
        $bg = $this->parseHex($options['bg'] ?? 'F9F9F9', 'F9F9F9');
        $logoText = $options['logoText'] ?? "QUIZ\nRACE";

        $logoPath = null;
        $font = $this->getFontFile();
        if ($font !== null && extension_loaded('gd')) {
            $logoPath = $this->createTextLogo($logoText, $font, self::FONT_SIZE_DEF, [0, 0, 0]);
        }

        $result = $this->renderQr($data, [
            'format' => $format,
            'size' => self::QR_SIZE_DEF,
            'margin' => self::QR_MARGIN_DEF,
            'ecc' => EccLevel::M,
            'fg' => $fg,
            'bg' => $bg,
            'logoPath' => $logoPath,
            'logoWidth' => self::LOGO_WIDTH_DEF,
        ]);

        if ($logoPath !== null && file_exists($logoPath)) {
            @unlink($logoPath);
        }

        return $result;
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
     * @param array{t:string,fg:string,text1?:string,text2?:string,logo_path?:string,logo_width?:int} $defaults
     * @return array{mime:string,body:string}
     */
    private function buildQrWithCenterLogoParam(array $q, array $defaults): array
    {
        $data = (string)($q['t'] ?? $defaults['t']);
        $format = strtolower((string)($q['format'] ?? 'png'));
        if (!in_array($format, ['png', 'svg'], true)) {
            $format = 'png';
        }

        $size = $this->clampInt($q['size'] ?? null, 64, 2048, self::QR_SIZE_DEF);
        $margin = $this->clampInt($q['margin'] ?? null, 0, 40, self::QR_MARGIN_DEF);
        $fg = $this->parseHex((string)($q['fg'] ?? $defaults['fg']));
        $bg = $this->parseHex((string)($q['bg'] ?? 'ffffff'), 'ffffff');

        $logoW = $this->clampInt($q['logo_width'] ?? ($defaults['logo_width'] ?? null), 20, 200, self::LOGO_WIDTH_DEF);
        $fontSz = $this->clampInt($q['font_size'] ?? null, 8, 48, self::FONT_SIZE_DEF);
        $text1 = (string)($q['text1'] ?? ($defaults['text1'] ?? 'QUIZ'));
        $text2 = (string)($q['text2'] ?? ($defaults['text2'] ?? 'RACE'));

        $ecParam = strtolower((string)($q['ec'] ?? 'medium'));
        $ec = match ($ecParam) {
            'low' => EccLevel::L,
            'quartile' => EccLevel::Q,
            'high', 'h' => EccLevel::H,
            default => EccLevel::M,
        };

        $logoPath = null;
        $logoParam = (string)($q['logo_path'] ?? ($defaults['logo_path'] ?? ''));
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

        $out = $this->renderQr($data, [
            'format' => $format,
            'size' => $size,
            'margin' => $margin,
            'ecc' => $ec,
            'fg' => $fg,
            'bg' => $bg,
            'logoPath' => $logoPath,
            'logoWidth' => $logoW,
        ]);

        if ($logoPath !== null && file_exists($logoPath)) {
            @unlink($logoPath);
        }

        return $out;
    }

    /**
     * @param array{format:string,size:int,margin:int,ecc:int,fg:array{0:int,1:int,2:int},bg:array{0:int,1:int,2:int},logoPath:?string,logoWidth:int} $p
     * @return array{mime:string,body:string}
     */
    private function renderQr(string $data, array $p): array
    {
        $scale = max(1, (int)round($p['size'] / 41));
        $marginModules = max(0, (int)round($p['margin'] / $scale));
        $options = [
            'eccLevel' => $p['ecc'],
            'scale' => $scale,
            'quietzoneSize' => $marginModules,
            'outputBase64' => false,
        ];

        if ($p['format'] === 'svg') {
            $options['outputType'] = QROutputInterface::MARKUP_SVG;
            $options['bgColor'] = sprintf('#%02x%02x%02x', $p['bg'][0], $p['bg'][1], $p['bg'][2]);
            $fg = sprintf('#%02x%02x%02x', $p['fg'][0], $p['fg'][1], $p['fg'][2]);
            $options['moduleValues'] = [
                QRMatrix::M_FINDER_DARK => $fg,
                QRMatrix::M_DARKMODULE => $fg,
                QRMatrix::M_DATA_DARK => $fg,
            ];
            $qr = new QRCode(new QROptions($options));
            $svg = $qr->render($data);
            if ($p['logoPath'] !== null && is_readable($p['logoPath'])) {
                $matrix = $qr->getMatrix();
                $dim = ($matrix->getSize() + 2 * $marginModules) * $scale;
                $logoData = base64_encode(file_get_contents($p['logoPath']));
                $x = (int)(($dim - $p['logoWidth']) / 2);
                $y = (int)(($dim - $p['logoWidth']) / 2);
                $image = '<image x="' . $x . '" y="' . $y . '" width="' . $p['logoWidth'] . '" height="' . $p['logoWidth'] . '" href="data:image/png;base64,' . $logoData . '" />';
                $svg = preg_replace('/<\/svg>/', $image . '</svg>', $svg);
            }
            return ['mime' => 'image/svg+xml', 'body' => $svg];
        }

        $options['outputType'] = QROutputInterface::GDIMAGE_PNG;
        $options['returnResource'] = true;
        $options['bgColor'] = $p['bg'];
        $options['moduleValues'] = [
            QRMatrix::M_FINDER_DARK => $p['fg'],
            QRMatrix::M_DARKMODULE => $p['fg'],
            QRMatrix::M_DATA_DARK => $p['fg'],
        ];

        $qr = new QRCode(new QROptions($options));
        /** @var GdImage $im */
        $im = $qr->render($data);
        if ($p['logoPath'] !== null && is_readable($p['logoPath'])) {
            $ext = strtolower(pathinfo($p['logoPath'], PATHINFO_EXTENSION));
            $logo = match ($ext) {
                'png' => @imagecreatefrompng($p['logoPath']),
                'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($p['logoPath']) : false,
                default => false,
            };
            if ($logo !== false) {
                $lw = imagesx($logo);
                $lh = imagesy($logo);
                $targetW = $p['logoWidth'];
                $targetH = (int)($lh * $targetW / $lw);
                $x = (imagesx($im) - $targetW) / 2;
                $y = (imagesy($im) - $targetH) / 2;
                $bgCol = imagecolorallocate($im, $p['bg'][0], $p['bg'][1], $p['bg'][2]);
                imagefilledrectangle($im, (int)$x, (int)$y, (int)($x + $targetW), (int)($y + $targetH), $bgCol);
                imagecopyresampled($im, $logo, (int)$x, (int)$y, 0, 0, $targetW, $targetH, $lw, $lh);
                imagedestroy($logo);
            }
        }
        ob_start();
        imagepng($im);
        $body = (string)ob_get_clean();
        imagedestroy($im);
        return ['mime' => 'image/png', 'body' => $body];
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
            $y += $lineHeights[$index];
            imagettftext($img, $fontSize, 0, 0, $y, $textColor, $fontFile, $line);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'qrlogo_') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);
        return $tmp;
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
            $defaults['text1'] = (string)$cfg['qrLabelLine1'];
        }
        if (($cfg['qrLabelLine2'] ?? '') !== '') {
            $defaults['text2'] = (string)$cfg['qrLabelLine2'];
        }
        if (($cfg['qrLogoPath'] ?? '') !== '') {
            $defaults['logo_path'] = (string)$cfg['qrLogoPath'];
        }
        if (($cfg['qrLogoWidth'] ?? '') !== '') {
            $defaults['logo_width'] = (int)$cfg['qrLogoWidth'];
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

    private function clampInt(mixed $v, int $min, int $max, int $def): int
    {
        $i = filter_var($v, FILTER_VALIDATE_INT);
        if ($i === false) {
            $i = $def;
        }
        return max($min, min($max, $i));
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
     * Create a PNG logo with one or two lines of centered text and a bordered background.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function createTextLogoPng(string $line1, string $line2, string $fontFile, int $fontSize, array $rgb): string
    {
        $padding = 10;
        $lineHeight = $fontSize + 6;
        $measure = function (string $t) use ($fontFile, $fontSize): int {
            $bb = imagettfbbox($fontSize, 0, $fontFile, $t);
            return abs($bb[2] - $bb[0]);
        };

        $lines = array_values(array_filter([$line1, $line2], fn($t) => $t !== ''));
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
            $x = (int)(($width - $tw) / 2);
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
