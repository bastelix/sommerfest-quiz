<?php

declare(strict_types=1);

namespace App\Service;

use Mpdf\Mpdf;
use function class_exists;
use function strlen;

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

        foreach ($catalogs as $catalog) {
            $name = (string)($catalog['name'] ?? $catalog['id'] ?? '');
            $desc = (string)($catalog['description'] ?? $catalog['beschreibung'] ?? '');
            $url = '?katalog=' . urlencode((string)($catalog['id'] ?? ''));

            $qrImg = '';
            if (class_exists('Endroid\\QrCode\\QrCode') && class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {
                $qrCode = \Endroid\QrCode\QrCode::create($url);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $qrImg = $writer->write($qrCode)->getDataUri();
            }

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($name) . '</td>';
            $html .= '<td>' . htmlspecialchars($desc) . '</td>';
            if ($qrImg !== '') {
                $html .= '<td><img src="' . $qrImg . '" width="60"/></td>';
            } else {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        $mpdf = new Mpdf(['tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', 'S');

        return $content;
    }
}
