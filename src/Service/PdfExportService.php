<?php

declare(strict_types=1);

namespace App\Service;

class PdfExportService
{
    private function createPageImage(string $label, string $qrData): string
    {
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrData);
        $qrContent = @file_get_contents($qrUrl);
        if ($qrContent === false) {
            $qrContent = '';
        }
        $qrImg = $qrContent ? imagecreatefromstring($qrContent) : false;
        $width = 595;
        $height = 842;
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        imagestring($img, 5, 30, 30, $label, $black);
        if ($qrImg !== false) {
            $w = imagesx($qrImg);
            $h = imagesy($qrImg);
            imagecopyresampled($img, $qrImg, ($width - 200) / 2, 100, 0, 0, 200, 200, $w, $h);
            imagedestroy($qrImg);
        }
        ob_start();
        imagejpeg($img, null, 90);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data === false ? '' : $data;
    }

    /**
     * @param list<array{id:string,name?:string}> $catalogs
     * @param list<string> $teams
     */
    public function build(array $catalogs, array $teams, string $baseUrl): string
    {
        $pdf = new SimplePdf();
        foreach ($catalogs as $cat) {
            $label = 'Katalog: ' . ($cat['name'] ?? $cat['id']);
            $data = $this->createPageImage($label, $baseUrl . '/?katalog=' . urlencode($cat['id']));
            $pdf->addPage([[
                'data' => $data,
                'width' => 595,
                'height' => 842,
                'x' => 0,
                'y' => 0,
            ]]);
        }
        foreach ($teams as $team) {
            $label = 'Team: ' . $team;
            $data = $this->createPageImage($label, $team);
            $pdf->addPage([[
                'data' => $data,
                'width' => 595,
                'height' => 842,
                'x' => 0,
                'y' => 0,
            ]]);
        }
        return $pdf->output();
    }
}

