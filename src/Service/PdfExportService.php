<?php

declare(strict_types=1);

namespace App\Service;

class PdfExportService
{
    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $catalogs
     */
    public function build(array $config, array $catalogs): string
    {
        $header = (string)($config['header'] ?? '');
        $subheader = (string)($config['subheader'] ?? '');

        $y = 770.0;
        $lines = [];
        $lines[] = 'BT';
        if ($header !== '') {
            $lines[] = '/F1 24 Tf';
            $lines[] = sprintf('1 0 0 1 72 %.1f Tm (%s) Tj', $y, $this->encode($header));
            $y -= 32.0;
        }
        if ($subheader !== '') {
            $lines[] = '/F1 16 Tf';
            $lines[] = sprintf('1 0 0 1 72 %.1f Tm (%s) Tj', $y, $this->encode($subheader));
            $y -= 40.0;
        }
        $lines[] = '/F1 12 Tf';
        foreach ($catalogs as $cat) {
            $name = (string)($cat['name'] ?? $cat['id'] ?? '');
            $lines[] = sprintf(
                '1 0 0 1 72 %.1f Tm (%s) Tj',
                $y,
                $this->encode('• ' . $name)
            );
            $y -= 20.0;
        }
        $lines[] = 'ET';

        $stream = implode("\n", $lines);

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>"; // 1
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>"; // 2
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>"; //3
        $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream"; //4
        // Use WinAnsiEncoding so encoded text renders correctly
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>"; //5

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf('%010d 00000 n \n', $off);
        }
        $pdf .= "trailer << /Root 1 0 R /Size " . (count($objects) + 1) . " >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF\n";

        return $pdf;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function encode(string $text): string
    {
        $encoded = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = '';
        }
        return $this->escape($encoded);
    }
}
