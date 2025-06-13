<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Alignment\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeMode;
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
     * Create a temporary QR code image for the given text.
     *
     * @param array<int,string> $tmpFiles
     */
    private function createQrImage(string $text, array &$tmpFiles): string
    {
        $builder = Builder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->encoding(new Encoding('UTF-8'))
            ->size(300)
            ->margin(20)
            ->backgroundColor(new Color(255, 255, 255))
            ->foregroundColor(new Color(35, 180, 90))
            ->labelText($text)
            ->labelFont(new NotoSans(20));

        if (class_exists(\Endroid\QrCode\Label\Alignment\LabelAlignmentCenter::class)) {
            $builder = $builder->labelAlignment(new \Endroid\QrCode\Label\Alignment\LabelAlignmentCenter());
        }

        $result = $builder
            ->roundBlockSizeMode(RoundBlockSizeMode::ENLARGE)
            ->build();

        $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
        $result->saveToFile($tmp);
        $tmpFiles[] = $tmp;
        return $tmp;
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
        $qrAvailable = class_exists(\Endroid\QrCode\QrCode::class)
            && class_exists(\Endroid\QrCode\Writer\PngWriter::class);

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

            $loginUrl = (string)($config['loginUrl'] ?? $config['login_url'] ?? '');
            if ($loginUrl !== '' && $qrAvailable) {
                $tmp = $this->createQrImage($loginUrl, $tmpFiles);
                $x = ($pdf->GetPageWidth() - 30) / 2;
                $y = $pdf->GetY();
                $pdf->Image($tmp, $x, $y, 30);
                $pdf->Ln(32);
            }

        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, $this->enc('Kataloge'));
        $pdf->Ln();

        // Always display the QR column. If the QR library is missing the cells
        // remain empty and no codes are generated.
        $qrEnabled = true;

        $pdf->SetFont('Arial', 'B', 12);
        $rowHeight = 20; // allow enough space for scannable QR codes
        $qrSize = 18;

        $pdf->Cell(40, $rowHeight, $this->enc('Name'), 1);
        $pdf->Cell(70, $rowHeight, $this->enc('Beschreibung'), 1);
        if ($qrEnabled) {
            $pdf->Cell(40, $rowHeight, $this->enc('QR-Text'), 1);
            $pdf->Cell(40, $rowHeight, $this->enc('QR-Code'), 1);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        foreach ($catalogs as $catalog) {
            $name = $this->enc((string)($catalog['name'] ?? $catalog['id'] ?? ''));
            $desc = $this->enc((string)($catalog['description'] ?? $catalog['beschreibung'] ?? ''));

            $pdf->Cell(40, $rowHeight, $name, 1);
            $pdf->Cell(70, $rowHeight, $desc, 1);

            if ($qrEnabled) {
                $qrImage = $catalog['qr_image']
                    ?? $catalog['qr']
                    ?? $catalog['qrcode_url']
                    ?? $catalog['qrcode']
                    ?? null;
                $qrImage = $this->loadQrImage($qrImage, $tmpFiles);
                $qrData = '?katalog=' . urlencode((string)($catalog['id'] ?? ''));
                $pdf->Cell(40, $rowHeight, $this->enc($qrData), 1);
                if ($qrImage === null && $qrAvailable) {
                    $url = '?katalog=' . urlencode((string)($catalog['id'] ?? ''));
                    $qrImage = $this->createQrImage($url, $tmpFiles);
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
            $pdf->Cell(70, $rowHeight, $this->enc('Name'), 1);
            if ($qrEnabled) {
                $pdf->Cell(80, $rowHeight, $this->enc('QR-Text'), 1);
                $pdf->Cell(40, $rowHeight, $this->enc('QR-Code'), 1);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 12);
            foreach ($teams as $team) {
            $name = is_array($team) ? $this->enc((string)($team['name'] ?? '')) : $this->enc((string)$team);
            $pdf->Cell(70, $rowHeight, $name, 1);
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
                    $qrData = is_array($team) ? (string)($team['name'] ?? '') : (string)$team;
                    $pdf->Cell(80, $rowHeight, $this->enc($qrData), 1);
                    if ($qrImage === null && $qrAvailable) {
                        $url = is_array($team) ? (string)($team['name'] ?? '') : (string)$team;
                        $qrImage = $this->createQrImage($url, $tmpFiles);
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
