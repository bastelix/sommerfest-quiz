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

        $qrEnabled = $qrAvailable;
        foreach ($catalogs as $c) {
            if (!empty($c['qr_image'] ?? $c['qr'] ?? null)) {
                $qrEnabled = true;
                break;
            }
        }
        if (!$qrEnabled) {
            foreach ($teams as $t) {
                if (is_array($t) && !empty($t['qr_image'] ?? $t['qr'] ?? null)) {
                    $qrEnabled = true;
                    break;
                }
            }
        }

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(60, 10, $this->enc('Name'), 1);
        $pdf->Cell(80, 10, $this->enc('Beschreibung'), 1);
        if ($qrEnabled) {
            $pdf->Cell(40, 10, $this->enc('QR-Code'), 1);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        $tmpFiles = [];
        foreach ($catalogs as $catalog) {
            $name = $this->enc((string)($catalog['name'] ?? $catalog['id'] ?? ''));
            $desc = $this->enc((string)($catalog['description'] ?? $catalog['beschreibung'] ?? ''));

            $pdf->Cell(60, 10, $name, 1);
            $pdf->Cell(80, 10, $desc, 1);

            if ($qrEnabled) {
                $qrImage = $catalog['qr_image'] ?? $catalog['qr'] ?? null;
                $tmp = null;
                if (is_string($qrImage) && $qrImage !== '') {
                    if (preg_match('/^data:image\/(png|jpeg);base64,/', $qrImage)) {
                        $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                        $data = substr($qrImage, strpos($qrImage, ',') + 1);
                        file_put_contents($tmp, base64_decode($data) ?: '');
                        $tmpFiles[] = $tmp;
                        $qrImage = $tmp;
                    } elseif (file_exists($qrImage)) {
                        $qrImage = $qrImage;
                    } else {
                        $qrImage = null;
                    }
                }
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
                $pdf->Cell(40, 10, '', 1);
                if ($qrImage !== null) {
                    $pdf->Image($qrImage, $x + 1, $y + 1, 8);
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
            $pdf->Cell(60, 10, $this->enc('Name'), 1);
            if ($qrEnabled) {
                $pdf->Cell(40, 10, $this->enc('QR-Code'), 1);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 12);
            foreach ($teams as $team) {
            $name = $this->enc((string)$team);
            $pdf->Cell(60, 10, $name, 1);
                if ($qrEnabled) {
                    $qrImage = null;
                    if (is_array($team)) {
                        $qrImage = $team['qr_image'] ?? $team['qr'] ?? null;
                    }
                    $tmp = null;
                    if (is_string($qrImage) && $qrImage !== '') {
                        if (preg_match('/^data:image\/(png|jpeg);base64,/', $qrImage)) {
                            $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
                            $data = substr($qrImage, strpos($qrImage, ',') + 1);
                            file_put_contents($tmp, base64_decode($data) ?: '');
                            $tmpFiles[] = $tmp;
                            $qrImage = $tmp;
                        } elseif (file_exists($qrImage)) {
                            $qrImage = $qrImage;
                        } else {
                            $qrImage = null;
                        }
                    }
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
                    $pdf->Cell(40, 10, '', 1);
                    if ($qrImage !== null) {
                        $pdf->Image($qrImage, $x + 1, $y + 1, 8);
                    }
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
