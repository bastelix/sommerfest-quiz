<?php

declare(strict_types=1);

namespace App\Service;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Mode;
use chillerlan\QRCode\Data\Byte;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;

use function base64_encode;
use function extension_loaded;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function imagecolorallocate;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagefilledrectangle;
use function imagecopyresampled;
use function imagepng;
use function imagesx;
use function imagesy;
use function ob_get_clean;
use function ob_start;
use function pathinfo;
use function preg_replace;
use function realpath;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function unlink;

class QrCodeService
{
    private const QR_SIZE_DEF = 360;
    private const QR_MARGIN_DEF = 40;
    private const LOGO_WIDTH_DEF = 120;

    /**
     * Generate a QR code with the project's default styling.
     *
     * @param array{fg?:string,bg?:string} $options
     * @return array{mime:string,body:string}
     */
    public function generateQrCode(string $data, string $format = 'svg', array $options = []): array {
        $fg = $this->parseHex($options['fg'] ?? '2E2E2E', '2E2E2E');
        $bg = $this->parseHex($options['bg'] ?? 'F9F9F9', 'F9F9F9');

        $result = $this->renderQr($data, [
            'format' => $format,
            'size' => self::QR_SIZE_DEF,
            'margin' => self::QR_MARGIN_DEF,
            'ecc' => EccLevel::M,
            'fg' => $fg,
            'bg' => $bg,
            'logoPath' => null,
            'logoWidth' => 0,
            'logoPunchout' => false,
        ]);

        return $result;
    }

    /**
     * @return array{mime:string,body:string}
     */
    public function generateCatalog(array $q, array $cfg = []): array {
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
    public function generateTeam(array $q, array $cfg = []): array {
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
    public function generateEvent(array $q, array $cfg = []): array {
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
     *     t: string,
     *     fg: string,
     *     logo_path?: string,
     *     logo_width?: int,
     *     logo_punchout?: bool,
     * } $defaults
     * @return array{mime:string,body:string}
     */
    private function buildQrWithCenterLogoParam(array $q, array $defaults): array {
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
        $logoPunchout = !in_array(
            strtolower((string)($q['logo_punchout'] ?? ($defaults['logo_punchout'] ?? '1'))),
            ['0', 'false', 'off'],
            true
        );

        $ecParam = strtolower((string)($q['ec'] ?? 'medium'));
        $ec = match ($ecParam) {
            'low' => EccLevel::L,
            'quartile' => EccLevel::Q,
            'high', 'h' => EccLevel::H,
            default => EccLevel::M,
        };

        $dataDir = realpath(__DIR__ . '/../../data') ?: __DIR__ . '/../../data';
        $dataDir = rtrim($dataDir, '/');
        $tmpDir = $dataDir . '/tmp';

        $logoPath = null;
        $deleteLogoAfterRender = false;
        $logoParam = (string)($q['logo_path'] ?? ($defaults['logo_path'] ?? ''));
        if ($logoParam !== '') {
            $candidate = $dataDir . (str_starts_with($logoParam, '/') ? $logoParam : '/' . $logoParam);
            $resolved = realpath($candidate);
            if ($resolved !== false && is_readable($resolved) && str_starts_with($resolved, $dataDir . '/')) {
                $logoPath = $resolved;
                $deleteLogoAfterRender = str_starts_with($resolved, rtrim($tmpDir, '/') . '/');
            }
        }
        // no default logo if none provided

        $out = $this->renderQr($data, [
            'format' => $format,
            'size' => $size,
            'margin' => $margin,
            'ecc' => $ec,
            'fg' => $fg,
            'bg' => $bg,
            'logoPath' => $logoPath,
            'logoWidth' => $logoPath ? $logoW : 0,
            'logoPunchout' => $logoPath ? $logoPunchout : false,
        ]);

        if ($deleteLogoAfterRender && $logoPath !== null && file_exists($logoPath)) {
            @unlink($logoPath);
        }

        return $out;
    }

    /**
     * @param array{
     *     format: string,
     *     size: int,
     *     margin: int,
     *     ecc: int,
     *     fg: array{0:int,1:int,2:int},
     *     bg: array{0:int,1:int,2:int},
     *     logoPath: ?string,
     *     logoWidth: int,
     *     logoPunchout: bool,
     * } $p
     * @return array{mime:string,body:string}
     */
    private function renderQr(string $data, array $p): array {
        $moduleCount = $this->resolveModuleCount($data, $p['ecc']);
        $scale = max(1, (int)round($p['size'] / max(1, $moduleCount + 4)));
        $marginModules = max(0, (int)round($p['margin'] / $scale));

        $options = [
            'eccLevel' => $p['ecc'],
            'addQuietzone' => true,
            'quietzoneSize' => $marginModules,
            'drawCircularModules' => true,
            'circleRadius' => 0.45,
            'drawSquareFinder' => true,
            'drawSquareAlignment' => true,
            'connectPaths' => true,
            'scale' => $scale,
            'outputBase64' => false,
        ];

        if ($p['format'] === 'svg') {
            $options['outputType'] = QROutputInterface::MARKUP_SVG;
            $bgColor = sprintf('#%02x%02x%02x', $p['bg'][0], $p['bg'][1], $p['bg'][2]);
            $options['bgColor'] = $bgColor;
            $fg = sprintf('#%02x%02x%02x', $p['fg'][0], $p['fg'][1], $p['fg'][2]);
            $options['moduleValues'] = [
                QRMatrix::M_FINDER_DARK => $fg,
                QRMatrix::M_DARKMODULE => $fg,
                QRMatrix::M_DATA_DARK => $fg,
            ];
            $qr = new QRCode(new QROptions($options));
            $svg = $qr->render($data);
            if ($p['logoPath'] !== null && is_readable($p['logoPath'])) {
                $ext = strtolower(pathinfo($p['logoPath'], PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => null,
                };
                if ($mime !== null) {
                    $matrix = $qr->getMatrix();
                    $dim = ($matrix->getSize() + 2 * $marginModules) * $scale;
                    $logoData = base64_encode(file_get_contents($p['logoPath']));
                    $size = @getimagesize($p['logoPath']);
                    $lw = (int)($size[0] ?? 0);
                    $lh = (int)($size[1] ?? 0);
                    if ($lw <= 0 || $lh <= 0) {
                        $lw = $lh = 1; // prevent division by zero
                    }
                    $targetW = $p['logoWidth'];
                    $targetH = (int)($lh * $targetW / $lw);
                    $x = (int)(($dim - $targetW) / 2);
                    $y = (int)(($dim - $targetH) / 2);
                    $rect = '';
                    if ($p['logoPunchout']) {
                        $rect = sprintf(
                            '<rect x="%d" y="%d" width="%d" height="%d" fill="%s" />',
                            $x,
                            $y,
                            $targetW,
                            $targetH,
                            $bgColor
                        );
                    }
                    $image = sprintf(
                        '<image x="%d" y="%d" width="%d" height="%d" href="data:%s;base64,%s" />',
                        $x,
                        $y,
                        $targetW,
                        $targetH,
                        $mime,
                        $logoData
                    );
                    $svg = preg_replace('/<\/svg>/', $rect . $image . '</svg>', $svg);
                }
            }
            return ['mime' => 'image/svg+xml', 'body' => $svg];
        }

        if (!extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is required for PNG output');
        }
        if (
            $p['logoPath'] !== null &&
            is_readable($p['logoPath']) &&
            strtolower(pathinfo($p['logoPath'], PATHINFO_EXTENSION)) === 'webp' &&
            !function_exists('imagecreatefromwebp')
        ) {
            throw new \RuntimeException('WebP logo requires imagecreatefromwebp()');
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
                'webp' => @imagecreatefromwebp($p['logoPath']),
                default => false,
            };
            if ($logo !== false) {
                $lw = imagesx($logo);
                $lh = imagesy($logo);
                $targetW = $p['logoWidth'];
                $targetH = (int)($lh * $targetW / $lw);
                $x = (imagesx($im) - $targetW) / 2;
                $y = (imagesy($im) - $targetH) / 2;
                if ($p['logoPunchout']) {
                    $bgCol = imagecolorallocate($im, $p['bg'][0], $p['bg'][1], $p['bg'][2]);
                    imagefilledrectangle(
                        $im,
                        (int)$x,
                        (int)$y,
                        (int)($x + $targetW),
                        (int)($y + $targetH),
                        $bgCol
                    );
                }
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
     * Determine the number of modules required for the given data with the requested ECC level.
     */
    private function resolveModuleCount(string $data, int $eccLevel): int {
        $normalized = $data !== '' ? $data : '?';

        $options = new QROptions([
            'eccLevel' => $eccLevel,
            'addQuietzone' => false,
            'outputBase64' => false,
        ]);

        $qr = new QRCode($options);
        $segmentAdded = false;

        foreach (Mode::INTERFACES as $interface) {
            if ($interface::validateString($normalized)) {
                $qr->addSegment(new $interface($normalized));
                $segmentAdded = true;
                break;
            }
        }

        if (!$segmentAdded) {
            $qr->addSegment(new Byte($normalized));
        }

        $matrix = $qr->getQRMatrix();

        return max(1, $matrix->getSize());
    }

    /**
     * Merge stored design configuration into default parameters.
     *
     * @param array<string,mixed> $defaults
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function mergeDesignDefaults(array $defaults, array $cfg): array {
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
        if (array_key_exists('qrLogoPunchout', $cfg)) {
            $defaults['logo_punchout'] = $cfg['qrLogoPunchout'] !== false;
        }
        return $defaults;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function parseHex(string $hex, string $fallback = '000000'): array {
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

    private function clampInt(mixed $v, int $min, int $max, int $def): int {
        $i = filter_var($v, FILTER_VALIDATE_INT);
        if ($i === false) {
            $i = $def;
        }
        return max($min, min($max, $i));
    }
}
