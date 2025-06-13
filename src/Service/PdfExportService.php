<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use FPDF;

class PdfExportService
{
    /**
     * Convert UTF-8 strings to ISO-8859-1 as required by FPDF.
     */
    private function enc(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        return $converted !== false ? $converted : $text;
    }

    /**
     * Resolve a QR code image location.
     *
     * @param mixed             $source
     * @param array<int,string> &$tmpFiles
     */
    private function loadQrImage($source, array &$tmpFiles): ?string
    {
        if (!is_string($source) || $source === '') {
            return null;
        }

        if (preg_match('/^data:image\/(png|jpeg);base64,/', $source)) {
            $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
            $data = substr($source, strpos($source, ',') + 1);
            file_put_contents($tmp, base64_decode($data) ?: '');
            $tmpFiles[] = $tmp;
            return $tmp;
        }

        if (preg_match('/^https?:\/\//', $source)) {
            $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true);
            $ext = pathinfo(parse_url($source, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
            if ($ext === '') {
                $ext = 'png';
            }
            $tmp .= '.' . $ext;
            $data = @file_get_contents($source);
            if ($data !== false) {
                file_put_contents($tmp, $data);
                $tmpFiles[] = $tmp;
                return $tmp;
            }
            return null;
        }

        if (file_exists($source)) {
            return $source;
        }

        return null;
    }
    /**
     * Build PDF listing catalogs with optional QR codes and a team table.
     *
     * @param array<string,mixed> $config
     * @param array<int,array<string,mixed>> $catalogs
     * @param list<string> $teams
     */
    public function build(array $config, array $catalogs, array $teams = []): string
    {
        $header = $this->enc((string)($config['header'] ?? ''));
        $subheader = $this->enc((string)($config['subheader'] ?? ''));

        $pdf = new \FPDF();
        $pdf->AddPage();

        $tmpFiles = [];

        try {
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

        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, $this->enc('Kataloge'));
        $pdf->Ln();

        $qrAvailable = class_exists(\Endroid\QrCode\QrCode::class)
            && class_exists(\Endroid\QrCode\Writer\PngWriter::class);

        // Always display the QR column. If the QR library is missing the cells
        // remain empty and no codes are generated.
        $qrEnabled = true;

        $pdf->SetFont('Arial', 'B', 12);
        $rowHeight = 20; // allow enough space for scannable QR codes
        $qrSize = 18;

        $pdf->Cell(60, $rowHeight, $this->enc('Name'), 1);
        $pdf->Cell(80, $rowHeight, $this->enc('Beschreibung'), 1);
        if ($qrEnabled) {
            $pdf->Cell(40, $rowHeight, $this->enc('QR-Code'), 1);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        foreach ($catalogs as $catalog) {
            $name = $this->enc((string)($catalog['name'] ?? $catalog['id'] ?? ''));
            $desc = $this->enc((string)($catalog['description'] ?? $catalog['beschreibung'] ?? ''));

            $pdf->Cell(60, $rowHeight, $name, 1);
            $pdf->Cell(80, $rowHeight, $desc, 1);

            if ($qrEnabled) {
                $qrImage = $catalog['qr_image']
                    ?? $catalog['qr']
                    ?? $catalog['qrcode_url']
                    ?? $catalog['qrcode']
                    ?? null;
                $qrImage = $this->loadQrImage($qrImage, $tmpFiles);
                if ($qrImage === null && $qrAvailable) {
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
                    $qrImage = $tmp;
                }

                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->Cell(40, $rowHeight, '', 1);
                if ($qrImage !== null) {
                    $pdf->Image($qrImage, $x + 1, $y + 1, $qrSize);
                }
            }
            $pdf->Ln();
        }

        if ($teams !== []) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $this->enc('Teams/Personen'));
            $pdf->Ln();

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(60, $rowHeight, $this->enc('Name'), 1);
            if ($qrEnabled) {
                $pdf->Cell(40, $rowHeight, $this->enc('QR-Code'), 1);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 12);
            foreach ($teams as $team) {
            $name = $this->enc((string)$team);
            $pdf->Cell(60, $rowHeight, $name, 1);
                if ($qrEnabled) {
                    $qrImage = null;
                    if (is_array($team)) {
                        $qrImage = $team['qr_image']
                            ?? $team['qr']
                            ?? $team['qrcode_url']
                            ?? $team['qrcode']
                            ?? null;
                    }
                    $qrImage = $this->loadQrImage($qrImage, $tmpFiles);
                    if ($qrImage === null && $qrAvailable) {
                        $url = is_array($team) ? (string)($team['name'] ?? '') : (string)$team;
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
                        $qrImage = $tmp;
                    }
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    $pdf->Cell(40, $rowHeight, '', 1);
                    if ($qrImage !== null) {
                        $pdf->Image($qrImage, $x + 1, $y + 1, $qrSize);
                    }
                }
                $pdf->Ln();
            }
        }

        $content = $pdf->Output('', 'S');

        return $content;
        } finally {
            foreach ($tmpFiles as $f) {
                @unlink($f);
            }
        }
    }
}
