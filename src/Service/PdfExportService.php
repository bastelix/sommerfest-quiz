<?php

declare(strict_types=1);

namespace App\Service;

use FPDF;

class PdfExportService
{
    /**
     * Build PDF listing catalogs.
     *
     * @param array<string,mixed> $config
     * @param array<int,array<string,mixed>> $catalogs
     */
    public function build(array $config, array $catalogs): string
    {
        $header = (string)($config['header'] ?? '');
        $subheader = (string)($config['subheader'] ?? '');

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
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        foreach ($catalogs as $catalog) {
            $name = (string)($catalog['name'] ?? $catalog['id'] ?? '');
            $desc = (string)($catalog['description'] ?? $catalog['beschreibung'] ?? '');

            $pdf->Cell(60, 10, $name, 1);
            $pdf->Cell(80, 10, $desc, 1);
            $pdf->Ln();
        }

        $content = $pdf->Output('', 'S');

        return $content;
    }
}
