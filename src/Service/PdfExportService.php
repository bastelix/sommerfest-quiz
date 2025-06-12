<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use FPDF;

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

        $qrAvailable = class_exists(\Endroid\QrCode\QrCode::class) && class_exists(\Endroid\QrCode\Writer\PngWriter::class);

        $pdf = new \FPDF();
        $pdf->AddPage();

        if ($header !== '') {
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $header);
            $pdf->Ln();
        }
        if ($subheader !== '') {
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, $subheader);
            $pdf->Ln();
        }

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(60, 10, 'Name', 1);
        $pdf->Cell(80, 10, 'Beschreibung', 1);
        if ($qrAvailable) {
            $pdf->Cell(40, 10, 'QR-Code', 1);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        $tmpFiles = [];
        foreach ($catalogs as $catalog) {
            $name = (string)($catalog['name'] ?? $catalog['id'] ?? '');
            $desc = (string)($catalog['description'] ?? $catalog['beschreibung'] ?? '');

            $pdf->Cell(60, 10, $name, 1);
            $pdf->Cell(80, 10, $desc, 1);

            if ($qrAvailable) {
                $url = '?katalog=' . urlencode((string)($catalog['id'] ?? ''));
                $qrCode = QrCode::create($url);
                $writer = new PngWriter();
                $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                $writer->write($qrCode)->saveToFile($tmp);
                $tmpFiles[] = $tmp;

                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->Cell(40, 10, '', 1);
                $pdf->Image($tmp, $x + 1, $y + 1, 8);
            }
            $pdf->Ln();
        }

        foreach ($tmpFiles as $f) {
            @unlink($f);
        }

        $content = $pdf->Output('', 'S');

        return $content;
    }
}
