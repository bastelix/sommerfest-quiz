<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\QrCodeService;
use App\Service\UrlService;
use FPDF;
use Intervention\Image\ImageManager;
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
            'rows' => 8,
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
    ];

    private ConfigService $config;
    private EventService $events;
    private CatalogService $catalogs;
    private QrCodeService $qr;

    public function __construct(
        ConfigService $config,
        EventService $events,
        CatalogService $catalogs,
        QrCodeService $qr
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->catalogs = $catalogs;
        $this->qr = $qr;
    }

    public function pdf(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $template = (string)($params['template'] ?? 'avery_l7165');
        if (!isset(self::LABEL_TEMPLATES[$template])) {
            $template = 'avery_l7165';
        }
        $tpl = self::LABEL_TEMPLATES[$template];

        $printDesc = filter_var($params['print_desc'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $qrColor = preg_replace('/[^0-9A-Fa-f]/', '', (string)($params['qr_color'] ?? '000000'));
        $qrColor = str_pad(substr($qrColor, 0, 6), 6, '0');
        $qrSizePct = max(10, min(100, (int)($params['qr_size_pct'] ?? 42)));
        $descTop = isset($params['desc_top']) ? (float)$params['desc_top'] : 0.0;
        $descLeft = isset($params['desc_left']) ? (float)$params['desc_left'] : 0.0;
        $qrTop = isset($params['qr_top']) ? (float)$params['qr_top'] : null;
        $qrLeft = isset($params['qr_left']) ? (float)$params['qr_left'] : null;

        $uid = (string)($params['event_uid'] ?? ($params['event'] ?? ''));
        if ($uid === '') {
            $uid = $this->config->getActiveEventUid();
        }

        $cfg = $uid !== '' ? $this->config->getConfigForEvent($uid) : $this->config->getConfig();
        $event = $uid !== '' ? $this->events->getByUid($uid) : null;
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
        $descTop = max(0.0, min($innerMaxH, $descTop));
        $descLeft = max(0.0, min($innerMaxW, $descLeft));
        $innerW = $innerMaxW - $descLeft;
        $innerH = $innerMaxH - $descTop;
        $qrSize = min($innerW * $qrSizePct / 100.0, $innerH * 0.55);
        $qrPad = 2.0;
        $qrLeft = $qrLeft !== null
            ? max(0.0, min($innerMaxW - $qrSize, $qrLeft))
            : $innerMaxW - $qrPad - $qrSize;
        $qrTop = $qrTop !== null
            ? max(0.0, min($innerMaxH - $qrSize, $qrTop))
            : $innerMaxH - $qrPad - $qrSize;

        $pdf = new FPDF('P', 'mm', $tpl['page']);
        $pdf->SetMargins(0.0, 0.0, 0.0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

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
        $bgFile = __DIR__ . '/../../data/uploads/sticker-bg.png';
        $hasBg = file_exists($bgFile);
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
            if (($tpl['border'] ?? 0.0) > 0) {
                $pdf->SetDrawColor(221, 221, 221);
                $pdf->SetLineWidth($tpl['border'] * 0.352778);
                $pdf->Rect($x, $y, $tpl['label_w'], $tpl['label_h']);
            }

            $baseX = $x + $tpl['padding'];
            $baseY = $y + $tpl['padding'];
            $innerX = $baseX + $descLeft;
            $innerY = $baseY + $descTop;
            $textW = $innerW * 0.6;
            $maxTextH = $innerH - 6.0;

            $curY = $innerY;
            $linesData = [];
            if ($eventTitle !== '') {
                $linesData[] = ['Arial', 'B', 12, $eventTitle];
            }
            if ($eventDesc !== '') {
                $linesData[] = ['Arial', '', 10, $eventDesc];
            }
            $linesData[] = ['Arial', 'B', 11, (string)($cat['name'] ?? '')];
            $desc = (string)($cat['description'] ?? '');
            if ($printDesc && $desc !== '') {
                $linesData[] = ['Arial', '', 10, $desc];
            }

            foreach ($linesData as $data) {
                [$fam, $style, $size, $text] = $data;
                $pdf->SetFont($fam, $style, $size);
                $lineH = $size * 1.2 * 0.352778;
                $t = $this->sanitizePdfText($text);
                $height = $this->getMultiCellHeight($pdf, $t, $textW, $lineH);
                if ($curY + $height - $innerY > $maxTextH) {
                    break;
                }
                $pdf->SetXY($innerX, $curY);
                $pdf->MultiCell($textW, $lineH, $t);
                $curY += $height;
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

    public function uploadBackground(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        if (!isset($files['file'])) {
            $response->getBody()->write('missing file');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $file = $files['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write('upload error');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        if ($file->getSize() !== null && $file->getSize() > 5 * 1024 * 1024) {
            $response->getBody()->write('file too large');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $response->getBody()->write('unsupported file type');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }

        if (!class_exists('\\Intervention\\Image\\ImageManager')) {
            $response->getBody()->write('Intervention Image NICHT installiert');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }

        $dir = __DIR__ . '/../../data/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $target = $dir . '/sticker-bg.png';

        $manager = extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
        $stream = $file->getStream();
        $img = $manager->read($stream->detach());
        $img->save($target, 90);

        return $response->withStatus(204);
    }

    private function sanitizePdfText(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($converted === false) {
            return preg_replace('/[^\x00-\x7F]/', '?', $text);
        }
        return $converted;
    }

    private function getMultiCellHeight(FPDF $pdf, string $text, float $width, float $lineHeight): float
    {
        $tmp = clone $pdf;
        $tmp->SetXY(0, 0);
        $tmp->MultiCell($width, $lineHeight, $text);
        return $tmp->GetY();
    }
}
