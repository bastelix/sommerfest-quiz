<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use FPDF;

class PdfExportService
{
    /**
     * Build PDF listing catalogs with optional QR codes and a team table.
     *
     * @param array<string,mixed> $config
     * @param array<int,array<string,mixed>> $catalogs
     * @param list<string> $teams
     */
    public function build(array $config, array $catalogs, array $teams = []): string
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

        $qrAvailable = class_exists(\Endroid\QrCode\QrCode::class)
            && class_exists(\Endroid\QrCode\Writer\PngWriter::class);

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
                if (method_exists(QrCode::class, 'create')) {
                    $qrCode = QrCode::create($url);
                    $writer = new PngWriter();
                    $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                    $writer->write($qrCode)->saveToFile($tmp);
                } else {
                    $qrCode = new QrCode($url);
                    $writer = new PngWriter();
                    $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                    if (method_exists($writer, 'writeFile')) {
                        $writer->writeFile($qrCode, $tmp);
                    } else {
                        $writer->write($qrCode)->saveToFile($tmp);
                    }
                }
                $tmpFiles[] = $tmp;

                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->Cell(40, 10, '', 1);
                $pdf->Image($tmp, $x + 1, $y + 1, 8);
            }
            $pdf->Ln();
        }

        if ($teams !== []) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Teams/Personen');
            $pdf->Ln();

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(60, 10, 'Name', 1);
            if ($qrAvailable) {
                $pdf->Cell(40, 10, 'QR-Code', 1);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 12);
            foreach ($teams as $team) {
                $name = (string)$team;
                $pdf->Cell(60, 10, $name, 1);
                if ($qrAvailable) {
                    $url = $name;
                    if (method_exists(QrCode::class, 'create')) {
                        $qrCode = QrCode::create($url);
                        $writer = new PngWriter();
                        $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                        $writer->write($qrCode)->saveToFile($tmp);
                    } else {
                        $qrCode = new QrCode($url);
                        $writer = new PngWriter();
                        $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                        if (method_exists($writer, 'writeFile')) {
                            $writer->writeFile($qrCode, $tmp);
                        } else {
                            $writer->write($qrCode)->saveToFile($tmp);
                        }
                    }
                    $tmpFiles[] = $tmp;
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    $pdf->Cell(40, 10, '', 1);
                    $pdf->Image($tmp, $x + 1, $y + 1, 8);
                }
                $pdf->Ln();
            }
        }

        foreach ($tmpFiles as $f) {
            @unlink($f);
        }

        $content = $pdf->Output('', 'S');

        return $content;
    }
}
