<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Minimal PDF builder supporting image pages.
 */
class SimplePdf
{
    /** @var list<string|null> */
    private array $objects = [];
    /** @var list<int> */
    private array $pages = [];

    public function __construct()
    {
        $this->objects[1] = null; // Catalog
        $this->objects[2] = null; // Pages
    }

    private function addObject(string $content): int
    {
        $id = count($this->objects) + 1;
        $this->objects[$id] = $content;
        return $id;
    }

    /**
     * @param array<int, array{data:string,width:int,height:int,x:int,y:int}> $images
     */
    public function addPage(array $images, int $width = 595, int $height = 842): void
    {
        $resourcesParts = [];
        foreach ($images as $i => $img) {
            $imgObj = $this->addObject(
                "<< /Type /XObject /Subtype /Image /Width {$img['width']} /Height {$img['height']} " .
                "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['data']) . " >>\n" .
                "stream\n{$img['data']}\nendstream"
            );
            $name = '/Im' . ($i + 1);
            $resourcesParts[] = $name . ' ' . $imgObj . ' 0 R';
            $images[$i]['name'] = $name;
        }

        $content = "q\n";
        foreach ($images as $img) {
            $x = $img['x'];
            $y = $height - $img['y'] - $img['height'];
            $name = $img['name'];
            $content .= "{$img['width']} 0 0 {$img['height']} $x $y cm\n{$name} Do\n";
        }
        $content .= "Q";
        $contentObj = $this->addObject("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream");
        $resources = "<< /XObject << " . implode(' ', $resourcesParts) . " >> >>";
        $pageObj = $this->addObject(
            "<< /Type /Page /Parent 2 0 R /Resources $resources /MediaBox [0 0 $width $height] /Contents $contentObj 0 R >>"
        );
        $this->pages[] = $pageObj;
    }

    public function output(): string
    {
        $pagesKids = implode(' ', array_map(fn($id) => "$id 0 R", $this->pages));
        $this->objects[2] = "<< /Type /Pages /Kids [$pagesKids] /Count " . count($this->pages) . " >>";
        $this->objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        for ($i = 1; $i <= count($this->objects); $i++) {
            $offsets[$i] = strlen($pdf);
            $content = $this->objects[$i];
            $pdf .= "$i 0 obj\n$content\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($this->objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($this->objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size " . (count($this->objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xref\n%%EOF";
        return $pdf;
    }
}

