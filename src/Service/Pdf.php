<?php

declare(strict_types=1);

namespace App\Service;

use FPDF;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * Custom FPDF subclass that renders the event header.
 */
class Pdf extends FPDF
{
    private string $title;
    private string $subtitle;
    private string $logoPath;
    private float $bodyStartY = 10.0;

    public function __construct(string $title, string $subtitle, string $logoPath = '')
    {
        parent::__construct();
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->logoPath = $logoPath;
    }

    /**
     * Render logo, title and subtitle at the top of each page.
     */
    public function Header(): void
    {
        $logoFile = $this->logoPath;
        $logoTemp = null;
        $qrSize = 20.0; // keep same dimensions as original header code
        $headerHeight = max(25.0, $qrSize + 5.0);

        if (is_readable($logoFile)) {
            if (str_ends_with(strtolower($logoFile), '.webp')) {
                $img = Image::make($logoFile);
                $logoTemp = tempnam(sys_get_temp_dir(), 'logo') . '.png';
                $img->encode('png')->save($logoTemp, 80);
                $logoFile = $logoTemp;
            }
            $this->Image($logoFile, 10, 10, $qrSize, $qrSize, 'PNG');
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

    public function getBodyStartY(): float
    {
        return $this->bodyStartY;
    }
}
