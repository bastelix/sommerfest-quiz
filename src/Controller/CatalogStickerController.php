<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\ImageUploadService;
use App\Service\NamespaceResolver;
use App\Service\QrCodeService;
use App\Service\UrlService;
use FPDF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class CatalogStickerController
{
    private const LABEL_TEMPLATES = [
        'avery_l7165' => [
            'page' => 'A4',
            'rows' => 4,
            'cols' => 2,
            'label_w' => 99.1,
            'label_h' => 67.7,
            'margin_top' => 10.0,
            'margin_left' => 5.0,
            'gutter_x' => 2.5,
            'gutter_y' => 2.5,
            'padding' => 6.0,
            'border' => 0.3,
        ],
        'avery_l7163' => [
            'page' => 'A4',
            'rows' => 7,
            'cols' => 2,
            'label_w' => 99.1,
            'label_h' => 38.1,
            'margin_top' => 10.0,
            'margin_left' => 5.0,
            'gutter_x' => 2.5,
            'gutter_y' => 2.5,
            'padding' => 5.0,
            'border' => 0.3,
        ],
        'avery_l7651' => [
            'page' => 'A4',
            'rows' => 7,
            'cols' => 3,
            'label_w' => 63.5,
            'label_h' => 38.1,
            'margin_top' => 10.0,
            'margin_left' => 7.0,
            'gutter_x' => 2.5,
            'gutter_y' => 2.5,
            'padding' => 4.0,
            'border' => 0.3,
        ],
        'avery_l7992' => [
            'page' => 'A4',
            'rows' => 5,
            'cols' => 1,
            'label_w' => 210.0,
            'label_h' => 41.0,
            'margin_top' => 10.0,
            'margin_left' => 0.0,
            'gutter_x' => 0.0,
            'gutter_y' => 2.5,
            'padding' => 6.0,
            'border' => 0.3,
        ],
        'avery_j8165' => [
            'page' => 'A4',
            'rows' => 4,
            'cols' => 1,
            'label_w' => 199.6,
            'label_h' => 67.7,
            'margin_top' => 10.0,
            'margin_left' => 5.2,
            'gutter_x' => 0.0,
            'gutter_y' => 2.5,
            'padding' => 6.0,
            'border' => 0.3,
        ],
        'avery_l7168' => [
            'page' => 'A4',
            'rows' => 2,
            'cols' => 1,
            'label_w' => 199.6,
            'label_h' => 143.5,
            'margin_top' => 5.0,
            'margin_left' => 5.2,
            'gutter_x' => 0.0,
            'gutter_y' => 2.5,
            'padding' => 6.0,
            'border' => 0.3,
        ],
    ];

    private ConfigService $config;
    private EventService $events;
    private CatalogService $catalogs;
    private QrCodeService $qr;
    private ImageUploadService $images;

    public function __construct(
        ConfigService $config,
        EventService $events,
        CatalogService $catalogs,
        QrCodeService $qr,
        ?ImageUploadService $images = null
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->catalogs = $catalogs;
        $this->qr = $qr;
        $this->images = $images ?? new ImageUploadService(sys_get_temp_dir());
    }

    private function pct(float|string|null $v): float {
        $f = (float) $v;
        if (!is_finite($f)) {
            $f = 0.0;
        }
        return max(0.0, min(100.0, $f));
    }

    public function getSettings(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $uid = (string) ($params['event_uid'] ?? '');
        if ($uid === '') {
            // default to active event when no UID is provided
            $uid = $this->config->getActiveEventUid();
        }
        $cfg = $uid !== ''
            ? $this->config->getConfigForEvent($uid)
            : $this->config->getConfig();

        $bgPath = null;
        if ($uid !== '') {
            $expected = $this->config->getEventImagesPath($uid) . '/sticker-bg.png';
            $abs = dirname(__DIR__, 2) . '/data' . $expected;
            if (is_file($abs)) {
                $bgPath = $expected;
                if (($cfg['stickerBgPath'] ?? null) !== $bgPath) {
                    $this->config->saveConfig(['event_uid' => $uid, 'stickerBgPath' => $bgPath]);
                }
            } elseif (!empty($cfg['stickerBgPath'])) {
                $this->config->saveConfig(['event_uid' => $uid, 'stickerBgPath' => null]);
            }
        }

        $printHeader = (bool)($cfg['stickerPrintHeader'] ?? true);
        $printSubheader = (bool)($cfg['stickerPrintSubheader'] ?? true);
        $printCatalog = (bool)($cfg['stickerPrintCatalog'] ?? true);
        $printDesc = (bool)($cfg['stickerPrintDesc'] ?? false);

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $event = $uid !== '' ? $this->events->getByUid($uid, $namespace) : null;
        $eventTitle = (string)($event['name'] ?? '');
        $eventDesc = (string)($event['description'] ?? '');
        $first = $this->catalogs->fetchPagedCatalogs(0, 1, 'asc');
        $cat = $first[0] ?? null;
        $catName = (string)($cat['name'] ?? '');
        $catDesc = (string)($cat['description'] ?? '');

        $data = [
            'stickerTemplate' => $cfg['stickerTemplate'] ?? 'avery_l7163',
            'stickerDescTop' => $cfg['stickerDescTop'] ?? 10,
            'stickerDescLeft' => $cfg['stickerDescLeft'] ?? 10,
            'stickerDescWidth' => $cfg['stickerDescWidth'] ?? 60,
            'stickerDescHeight' => $cfg['stickerDescHeight'] ?? 60,
            'stickerQrTop' => $cfg['stickerQrTop'] ?? 10,
            'stickerQrLeft' => $cfg['stickerQrLeft'] ?? 75,
            'stickerQrSizePct' => $cfg['stickerQrSizePct'] ?? 28,
            'stickerPrintHeader' => $printHeader,
            'stickerPrintSubheader' => $printSubheader,
            'stickerPrintCatalog' => $printCatalog,
            'stickerPrintDesc' => $printDesc,
            'stickerQrColor' => $cfg['stickerQrColor'] ?? '000000',
            'stickerTextColor' => $cfg['stickerTextColor'] ?? '000000',
            'stickerHeaderFontSize' => (int)($cfg['stickerHeaderFontSize'] ?? 12),
            'stickerSubheaderFontSize' => (int)($cfg['stickerSubheaderFontSize'] ?? 10),
            'stickerCatalogFontSize' => (int)($cfg['stickerCatalogFontSize'] ?? 11),
            'stickerDescFontSize' => (int)($cfg['stickerDescFontSize'] ?? 10),
            'stickerBgPath' => $bgPath,
            'previewHeader' => $eventTitle,
            'previewSubheader' => $eventDesc,
            'previewCatalog' => $catName,
            'previewDesc' => $catDesc,
        ];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function saveSettings(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $uid = (string) ($data['event_uid'] ?? '');
        if ($uid === '') {
            // fall back to active event or global configuration
            $uid = $this->config->getActiveEventUid();
        }
        $tpl = in_array(
            (string)($data['stickerTemplate'] ?? ''),
            ['avery_l7163', 'avery_l7165', 'avery_l7651', 'avery_l7992', 'avery_j8165', 'avery_l7168'],
            true
        )
            ? (string) $data['stickerTemplate']
            : 'avery_l7163';

        $qrColor = preg_replace('/[^0-9A-Fa-f]/', '', (string)($data['stickerQrColor'] ?? '000000'));
        $qrColor = substr(str_pad($qrColor, 6, '0'), 0, 6);
        $textColor = preg_replace('/[^0-9A-Fa-f]/', '', (string)($data['stickerTextColor'] ?? '000000'));
        $textColor = substr(str_pad($textColor, 6, '0'), 0, 6);

        $norm = fn ($v) => (float) str_replace(',', '.', (string) $v);

        $save = [
            'event_uid' => $uid,
            'stickerTemplate' => $tpl,
            'stickerDescTop' => $this->pct($norm($data['stickerDescTop'] ?? 10)),
            'stickerDescLeft' => $this->pct($norm($data['stickerDescLeft'] ?? 10)),
            'stickerDescWidth' => $this->pct($norm($data['stickerDescWidth'] ?? 60)),
            'stickerDescHeight' => $this->pct($norm($data['stickerDescHeight'] ?? 60)),
            'stickerQrTop' => $this->pct($norm($data['stickerQrTop'] ?? 10)),
            'stickerQrLeft' => $this->pct($norm($data['stickerQrLeft'] ?? 75)),
            'stickerQrSizePct' => $this->pct($norm($data['stickerQrSizePct'] ?? 28)),
            'stickerPrintHeader' => filter_var($data['stickerPrintHeader'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'stickerPrintSubheader' => filter_var($data['stickerPrintSubheader'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'stickerPrintCatalog' => filter_var($data['stickerPrintCatalog'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'stickerPrintDesc' => filter_var($data['stickerPrintDesc'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'stickerQrColor' => $qrColor,
            'stickerTextColor' => $textColor,
            'stickerHeaderFontSize' => (int)($data['stickerHeaderFontSize'] ?? 12),
            'stickerSubheaderFontSize' => (int)($data['stickerSubheaderFontSize'] ?? 10),
            'stickerCatalogFontSize' => (int)($data['stickerCatalogFontSize'] ?? 11),
            'stickerDescFontSize' => (int)($data['stickerDescFontSize'] ?? 10),
        ];
        $this->config->saveConfig($save);
        return $response->withStatus(204);
    }

    public function pdf(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $uid = (string)($params['event_uid'] ?? ($params['event'] ?? ''));
        if ($uid === '') {
            $uid = $this->config->getActiveEventUid();
        }

        $cfg = $uid !== '' ? $this->config->getConfigForEvent($uid) : $this->config->getConfig();
        $template = (string)($params['template'] ?? ($cfg['stickerTemplate'] ?? 'avery_l7163'));
        if (!isset(self::LABEL_TEMPLATES[$template])) {
            $template = 'avery_l7163';
        }
        $tpl = self::LABEL_TEMPLATES[$template];

        $printHeader = isset($params['print_header'])
            ? filter_var($params['print_header'], FILTER_VALIDATE_BOOLEAN)
            : (bool)($cfg['stickerPrintHeader'] ?? true);
        $printSubheader = isset($params['print_subheader'])
            ? filter_var($params['print_subheader'], FILTER_VALIDATE_BOOLEAN)
            : (bool)($cfg['stickerPrintSubheader'] ?? true);
        $printCatalog = isset($params['print_catalog'])
            ? filter_var($params['print_catalog'], FILTER_VALIDATE_BOOLEAN)
            : (bool)($cfg['stickerPrintCatalog'] ?? true);
        $printDesc = isset($params['print_desc'])
            ? filter_var($params['print_desc'], FILTER_VALIDATE_BOOLEAN)
            : (bool)($cfg['stickerPrintDesc'] ?? false);
        $qrColor = preg_replace(
            '/[^0-9A-Fa-f]/',
            '',
            (string)($params['qr_color'] ?? ($cfg['stickerQrColor'] ?? '000000'))
        );
        $qrColor = str_pad(substr($qrColor, 0, 6), 6, '0');
        $textColor = preg_replace(
            '/[^0-9A-Fa-f]/',
            '',
            (string)($params['text_color'] ?? ($cfg['stickerTextColor'] ?? '000000'))
        );
        $textColor = str_pad(substr($textColor, 0, 6), 6, '0');
        [$r, $g, $b] = array_map('hexdec', str_split($textColor, 2));
        $qrSizePct = isset($params['qr_size_pct'])
            ? (float)$params['qr_size_pct']
            : (float)($cfg['stickerQrSizePct'] ?? 42.0);
        $qrSizePct = max(10.0, min(100.0, $qrSizePct));
        $descTopPct = isset($params['desc_top'])
            ? (float)$params['desc_top']
            : (float)($cfg['stickerDescTop'] ?? 0.0);
        $descLeftPct = isset($params['desc_left'])
            ? (float)$params['desc_left']
            : (float)($cfg['stickerDescLeft'] ?? 0.0);
        $descWidthPct = isset($params['desc_width'])
            ? (float)$params['desc_width']
            : (isset($cfg['stickerDescWidth']) ? (float)$cfg['stickerDescWidth'] : null);
        $descHeightPct = isset($params['desc_height'])
            ? (float)$params['desc_height']
            : (isset($cfg['stickerDescHeight']) ? (float)$cfg['stickerDescHeight'] : null);
        $qrTopPct = isset($params['qr_top'])
            ? (float)$params['qr_top']
            : (isset($cfg['stickerQrTop']) ? (float)$cfg['stickerQrTop'] : null);
        $qrLeftPct = isset($params['qr_left'])
            ? (float)$params['qr_left']
            : (isset($cfg['stickerQrLeft']) ? (float)$cfg['stickerQrLeft'] : null);
        $headerSize = isset($params['header_size'])
            ? (int)$params['header_size']
            : (int)($cfg['stickerHeaderFontSize'] ?? 12);
        $subheaderSize = isset($params['subheader_size'])
            ? (int)$params['subheader_size']
            : (int)($cfg['stickerSubheaderFontSize'] ?? 10);
        $catalogSize = isset($params['catalog_size'])
            ? (int)$params['catalog_size']
            : (int)($cfg['stickerCatalogFontSize'] ?? 11);
        $descSize = isset($params['desc_size'])
            ? (int)$params['desc_size']
            : (int)($cfg['stickerDescFontSize'] ?? 10);
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $event = $uid !== '' ? $this->events->getByUid($uid, $namespace) : null;
        $eventTitle = (string)($event['name'] ?? '');
        $eventDesc = (string)($event['description'] ?? '');

        $baseUrl = UrlService::determineBaseUrl($request);

        $catsJson = $this->catalogs->read('catalogs.json');
        $catalogs = $catsJson ? json_decode($catsJson, true) : [];
        if (!is_array($catalogs)) {
            $catalogs = [];
        }

        $innerMaxW = $tpl['label_w'] - 2 * $tpl['padding'];
        $innerMaxH = $tpl['label_h'] - 2 * $tpl['padding'];
        $descTop = max(0.0, min(100.0, $descTopPct)) / 100.0 * $innerMaxH;
        $descLeft = max(0.0, min(100.0, $descLeftPct)) / 100.0 * $innerMaxW;
        $innerW = $innerMaxW - $descLeft;
        $innerH = $innerMaxH - $descTop;
        $descWidth = $descWidthPct !== null
            ? max(0.0, min(100.0, $descWidthPct)) / 100.0 * $innerMaxW
            : null;
        $descHeight = $descHeightPct !== null
            ? max(0.0, min(100.0, $descHeightPct)) / 100.0 * $innerMaxH
            : null;
        $descWidth = $descWidth !== null ? max(0.0, min($innerW, $descWidth)) : $innerW * 0.6;
        $descHeight = $descHeight !== null ? max(0.0, min($innerH, $descHeight)) : $innerH - 6.0;
        $qrSize = min($innerW, $innerH) * ($qrSizePct / 100.0);
        $qrPad = 2.0;
        $qrLeft = $qrLeftPct !== null
            ? max(0.0, min(100.0, $qrLeftPct)) / 100.0 * $innerMaxW
            : null;
        $qrTop = $qrTopPct !== null
            ? max(0.0, min(100.0, $qrTopPct)) / 100.0 * $innerMaxH
            : null;
        $qrLeft = $qrLeft !== null
            ? max(0.0, min($innerMaxW - $qrSize, $qrLeft))
            : $innerMaxW - $qrPad - $qrSize;
        $qrTop = $qrTop !== null
            ? max(0.0, min($innerMaxH - $qrSize, $qrTop))
            : $innerMaxH - $qrPad - $qrSize;

        if ($descWidth <= 0.0) {
            $descWidth = $innerW * 0.6;
        }
        if ($descHeight <= 0.0) {
            $descHeight = $innerH - 6.0;
        }
        if ($qrSize <= 0.0) {
            $qrSize = min($innerW, $innerH) * ($qrSizePct / 100.0);
        }
        if ($qrLeft <= 0.0) {
            $qrLeft = $innerMaxW - $qrPad - $qrSize;
        }
        if ($qrTop <= 0.0) {
            $qrTop = $innerMaxH - $qrPad - $qrSize;
        }

        $descWidth = max(0.0, min($innerW, $descWidth));
        $descHeight = max(0.0, min($innerH, $descHeight));
        $qrSize = max(0.0, min(min($innerW, $innerH), $qrSize));
        $qrLeft = max(0.0, min($innerMaxW - $qrSize, $qrLeft));
        $qrTop = max(0.0, min($innerMaxH - $qrSize, $qrTop));

        $pdf = new FPDF('P', 'mm', $tpl['page']);
        $pdf->SetMargins(0.0, 0.0, 0.0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetTextColor($r, $g, $b);

        if ($catalogs === []) {
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $this->sanitizePdfText('Keine Kataloge'), 0, 1, 'C');
            $out = $pdf->Output('S');
            $response->getBody()->write($out);
            return $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'inline; filename="catalog-stickers.pdf"');
        }

        $count = 0;
        $perPage = $tpl['rows'] * $tpl['cols'];
        $bgFile = '';
        if ($uid !== '') {
            $this->config->migrateEventImages($uid);
            $bgCandidate = $this->config->getEventImagesDir($uid) . '/sticker-bg.png';
            if (file_exists($bgCandidate)) {
                $bgFile = $bgCandidate;
            }
        }
        $hasBg = $bgFile !== '' && file_exists($bgFile);
        foreach ($catalogs as $cat) {
            if ($count > 0 && $count % $perPage === 0) {
                $pdf->AddPage();
            }
            $pos = $count % $perPage;
            $row = intdiv($pos, $tpl['cols']);
            $col = $pos % $tpl['cols'];
            $x = $tpl['margin_left'] + $col * ($tpl['label_w'] + $tpl['gutter_x']);
            $y = $tpl['margin_top'] + $row * ($tpl['label_h'] + $tpl['gutter_y']);
            if ($hasBg) {
                $pdf->Image($bgFile, $x, $y, $tpl['label_w'], $tpl['label_h']);
            }
            $pdf->SetDrawColor(221, 221, 221);
            $pdf->SetLineWidth($tpl['border'] * 0.352778);
            $pdf->Rect($x, $y, $tpl['label_w'], $tpl['label_h']);

            $baseX = $x + $tpl['padding'];
            $baseY = $y + $tpl['padding'];
            $innerX = $baseX + $descLeft;
            $innerY = $baseY + $descTop;
            $textW = $descWidth;
            $maxTextH = $descHeight;

            $curY = $innerY;
            $linesData = [];
            if ($printHeader && $eventTitle !== '') {
                $linesData[] = ['Arial', 'B', $headerSize, $eventTitle];
            }
            if ($printSubheader && $eventDesc !== '') {
                $linesData[] = ['Arial', '', $subheaderSize, $eventDesc];
            }
            if ($printCatalog) {
                $linesData[] = ['Arial', 'B', $catalogSize, (string)($cat['name'] ?? '')];
            }
            $desc = (string)($cat['description'] ?? '');
            if ($printDesc && $desc !== '') {
                $linesData[] = ['Arial', '', $descSize, $desc];
            }

            foreach ($linesData as $data) {
                [$fam, $style, $sizePx, $text] = $data;
                $pt = $sizePx * 72 / 96;
                $lineH = $sizePx * 1.2 * 0.264583;
                $pdf->SetFont($fam, $style, $pt);
                $t = $this->sanitizePdfText($text);
                $wrapped = $this->wrapText($pdf, $t, $textW);
                foreach ($wrapped as $line) {
                    if ($curY - $innerY + $lineH > $maxTextH) {
                        break 2;
                    }
                    $pdf->SetXY($innerX, $curY);
                    $pdf->Cell($textW, $lineH, $line);
                    $curY += $lineH;
                }
            }

            $qrX = $baseX + $qrLeft;
            $qrY = $baseY + $qrTop;

            $path = $uid !== ''
                ? '/?event=' . $uid . '&katalog=' . ($cat['slug'] ?? '')
                : '/?katalog=' . ($cat['slug'] ?? '');
            $link = $baseUrl . $path;
            $q = ['t' => $link, 'fg' => $qrColor, 'format' => 'png'];
            try {
                $qr = $this->qr->generateCatalog($q, $cfg);
                $tmp = tempnam(sys_get_temp_dir(), 'qr') . '.png';
                file_put_contents($tmp, $qr['body']);
                $pdf->Image($tmp, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
                unlink($tmp);
            } catch (Throwable $e) {
                $pdf->Rect($qrX, $qrY, $qrSize, $qrSize);
            }

            $count++;
        }

        $out = $pdf->Output('S');
        $response->getBody()->write($out);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="catalog-stickers.pdf"');
    }

    public function uploadBackground(Request $request, Response $response): Response {
        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            $response->getBody()->write('missing file');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $file = $files['file'];

        try {
            $this->images->validate(
                $file,
                5 * 1024 * 1024,
                ['png', 'jpg', 'jpeg', 'webp'],
                ['image/png', 'image/jpeg', 'image/webp']
            );
        } catch (\RuntimeException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $params = $request->getQueryParams();
        $uid = (string)($params['event_uid'] ?? '');
        if ($uid === '') {
            $uid = $this->config->getActiveEventUid();
        }

        $dir = $uid !== '' ? 'events/' . $uid . '/images' : 'uploads';
        try {
            $path = $this->images->saveUploadedFile(
                $file,
                $dir,
                'sticker-bg',
                2000,
                2000,
                ImageUploadService::QUALITY_STICKER,
                true
            );
        } catch (Throwable $e) {
            $response->getBody()->write('image processing failed');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        $this->config->saveConfig([
            'event_uid' => $uid,
            'stickerBgPath' => $path,
        ]);
        $response->getBody()->write(json_encode(['stickerBgPath' => $path]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function sanitizePdfText(string $text): string {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($converted === false) {
            return preg_replace('/[^\x00-\x7F]/', '?', $text);
        }
        return $converted;
    }

    private function wrapText(FPDF $pdf, string $text, float $maxWidth): array {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $w) {
            $test = $line === '' ? $w : $line . ' ' . $w;
            if ($pdf->GetStringWidth($test) > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $w;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }
}
