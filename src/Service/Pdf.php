<?php

declare(strict_types=1);

namespace App\Service;

use setasign\Fpdi\Fpdi;
use Intervention\Image\ImageManager;

/**
 * Custom FPDF subclass that renders the event header.
 */
class Pdf extends Fpdi
{
    private string $title;
    private string $subtitle;
    private string $logoPath;
    private float $bodyStartY = 10.0;

    public function __construct(string $title, string $subtitle, string $logoPath = '') {
        parent::__construct();
        $this->SetCompression(false);
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->logoPath = $logoPath;
    }

    /**
     * Render logo, title and subtitle at the top of each page.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Header(): void {
        $logoFile = $this->logoPath;
        $logoTemp = null;
        $qrSize = 20.0; // keep same dimensions as original header code
        $headerHeight = max(25.0, $qrSize + 5.0);

        if (is_file($logoFile) && is_readable($logoFile)) {
            $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
            if ($ext === 'webp') {
                $manager = extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
                $img = $manager->read($logoFile);
                $logoTemp = tempnam(sys_get_temp_dir(), 'logo') . '.png';
                $img->save($logoTemp, 80);
                $logoFile = $logoTemp;
                $ext = 'png';
            } elseif ($ext === 'svg') {
                $logoFile = '';
            }

            if ($logoFile !== '') {
                $type = strtoupper(pathinfo($logoFile, PATHINFO_EXTENSION));
                $this->Image($logoFile, 10, 10, $qrSize, $qrSize, $type);
            }
        }

        $this->SetXY(10, 10);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell($this->GetPageWidth() - 20, 8, $this->title, 0, 2, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell($this->GetPageWidth() - 20, 6, $this->subtitle, 0, 2, 'C');

        $y = 10 + $headerHeight - 2;
        $this->SetLineWidth(0.2);
        $this->Line(10, $y, $this->GetPageWidth() - 10, $y);

        $this->bodyStartY = $y + 5;
        $this->SetXY(10, $this->bodyStartY);

        if ($logoTemp !== null) {
            unlink($logoTemp);
        }
    }

    public function getBodyStartY(): float {
        return $this->bodyStartY;
    }
}
