<?php
// Simple demonstration to render HTML text with FPDF using existing fonts.
// Usage: php scripts/fpdf_example.php [inputFile]

require __DIR__ . '/../vendor/autoload.php';



$input = $argv[1] ?? __DIR__ . '/example_text.html';
if (!is_readable($input)) {
    fwrite(STDERR, "Input file not found: $input\n");
    exit(1);
}

// Load UTF-8 encoded text from file
$editorText = file_get_contents($input);

// Allow simple formatting tags
$textPlain = strip_tags($editorText, '<h2><br><p>');

// Convert to ISO-8859-1 for FPDF
$textForPdf = mb_convert_encoding($textPlain, 'ISO-8859-1', 'UTF-8');

$pdf = new \FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, $textForPdf);

$pdf->Output('I', 'editor-output.pdf');
