<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\EventService;
use App\Service\QrCodeService;
use App\Service\UrlService;
use FPDF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class CatalogStickerController
{
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
        $rows = max(1, (int)($params['rows'] ?? 2));
        $cols = max(1, (int)($params['cols'] ?? 2));
        $margin = max(0.0, (float)($params['margin'] ?? 8));
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

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins($margin, $margin, $margin);
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

        $pageW = $pdf->GetPageWidth();
        $pageH = $pdf->GetPageHeight();
        $gutter = 6.0;
        $cardW = ($pageW - 2 * $margin - ($cols - 1) * $gutter) / $cols;
        $cardH = ($pageH - 2 * $margin - ($rows - 1) * $gutter) / $rows;
        $pad = 4.0;
        $qrSize = $cardW * 0.4;

        $count = 0;
        foreach ($catalogs as $cat) {
            if ($count > 0 && $count % ($rows * $cols) === 0) {
                $pdf->AddPage();
            }
            $pos = $count % ($rows * $cols);
            $row = intdiv($pos, $cols);
            $col = $pos % $cols;
            $x = $margin + $col * ($cardW + $gutter);
            $y = $margin + $row * ($cardH + $gutter);

            $pdf->SetDrawColor(221, 221, 221);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($x, $y, $cardW, $cardH);

            $innerX = $x + $pad;
            $innerY = $y + $pad;
            $textW = $cardW - 2 * $pad - $qrSize - $pad;
            $pdf->SetXY($innerX, $innerY);

            if ($eventTitle !== '') {
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->MultiCell($textW, 6, $this->sanitizePdfText($eventTitle));
            }
            if ($eventDesc !== '') {
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell($textW, 5, $this->sanitizePdfText($eventDesc));
            }

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->MultiCell($textW, 5, $this->sanitizePdfText((string)($cat['name'] ?? '')));
            $desc = (string)($cat['description'] ?? '');
            if ($desc !== '') {
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell($textW, 5, $this->sanitizePdfText($desc));
            }

            $path = $uid !== ''
                ? '/?event=' . $uid . '&katalog=' . ($cat['slug'] ?? '')
                : '/?katalog=' . ($cat['slug'] ?? '');
            $link = $baseUrl . $path;
            $q = ['t' => $link, 'format' => 'png'];
            $qrX = $x + $cardW - $pad - $qrSize;
            $qrY = $y + ($cardH - $qrSize) / 2;
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

    private function sanitizePdfText(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($converted === false) {
            return preg_replace('/[^\x00-\x7F]/', '?', $text);
        }
        return $converted;
    }
}

