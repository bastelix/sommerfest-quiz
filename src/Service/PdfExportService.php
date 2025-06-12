<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Mpdf\Mpdf;

class PdfExportService
{
    /**
     * Build PDF listing catalogs with optional QR codes.
     *
     * @param array<string,mixed> $config
     * @param array<int,array<string,mixed>> $catalogs
     */
    public function build(array $config, array $catalogs): string
    {
        $header = (string)($config['header'] ?? '');
        $subheader = (string)($config['subheader'] ?? '');

        $html = '';
        if ($header !== '') {
            $html .= '<h1>' . htmlspecialchars($header) . '</h1>';
        }
        if ($subheader !== '') {
            $html .= '<h2>' . htmlspecialchars($subheader) . '</h2>';
        }

        $html .= '<table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">';
        $html .= '<tr><th>Name</th><th>Beschreibung</th><th>QR-Code</th></tr>';

        $tmpFiles = [];
        foreach ($catalogs as $catalog) {
            $name = (string)($catalog['name'] ?? $catalog['id'] ?? '');
            $desc = (string)($catalog['description'] ?? $catalog['beschreibung'] ?? '');
            $url = '?katalog=' . urlencode((string)($catalog['id'] ?? ''));
            $qrCode = QrCode::create($url);
            $writer = new PngWriter();
            $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
            $writer->write($qrCode)->saveToFile($tmp);
            $tmpFiles[] = $tmp;

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($name) . '</td>';
            $html .= '<td>' . htmlspecialchars($desc) . '</td>';
            $html .= '<td><img src="' . $tmp . '" width="60"/></td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        $mpdf = new Mpdf(['tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', 'S');

        foreach ($tmpFiles as $f) {
            @unlink($f);
        }

        return $content;
    }
}
