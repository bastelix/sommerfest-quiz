<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WebPWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode;
use FPDF;
use Intervention\Image\ImageManagerStatic as Image;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Generates QR codes with various customization options.
 */
class QrController
{
    private ConfigService $config;

    /**
     * Inject configuration service dependency.
     */
    public function __construct(ConfigService $config)
    {
        $this->config = $config;
    }

    /**
     * Render a QR code image based on query parameters.
     */
    public function image(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $text   = (string)($params['t'] ?? '');
        if ($text === '') {
            return $response->withStatus(400);
        }

        $fg     = (string)($params['fg'] ?? '23b45a');
        $bg     = (string)($params['bg'] ?? 'ffffff');
        $size   = (int)($params['s'] ?? 300);
        $margin = (int)($params['m'] ?? 20);
        $demo   = (string)($params['demo'] ?? '');
        $label  = (string)($params['label'] ?? '1');
        $useLabel = !in_array(strtolower($label), ['0', 'false', 'no'], true);

        $writer  = new PngWriter();
        if ($demo === 'svg' || $demo === 'svg-clean') {
            $writer = new SvgWriter();
        } elseif ($demo === 'webp') {
            $writer = new WebPWriter();
        }

        $builder = Builder::create()
            ->writer($writer)
            ->data($text)
            ->encoding(new Encoding('UTF-8'))
            ->size($size)
            ->margin($margin)
            ->backgroundColor($this->parseColor($bg, new Color(255, 255, 255)))
            ->foregroundColor($this->parseColor($fg, new Color(35, 180, 90)));

        if ($useLabel) {
            $builder = $builder
                ->labelText($text)
                ->labelFont(new NotoSans(20));
        }

        if ($demo === 'logo') {
            $builder = $builder
                ->logoPath(__DIR__ . '/../../public/favicon.svg')
                ->logoResizeToWidth(60)
                ->logoPunchoutBackground(true);
        } elseif ($demo === 'label' && $useLabel) {
            $builder = $builder
                ->labelText('Jetzt scannen!')
                ->labelFont(new NotoSans(22));
        } elseif ($demo === 'colors') {
            $builder = $builder
                ->foregroundColor(new Color(0, 102, 204))
                ->backgroundColor(new Color(240, 248, 255));
        } elseif ($demo === 'svg') {
            $builder = $builder->writerOptions(['svgRoundBlocks' => true]);
        } elseif ($demo === 'high') {
            $builder = $builder->errorCorrectionLevel(ErrorCorrectionLevel::High);
        } elseif ($demo === 'webp') {
            $builder = $builder->writerOptions(['quality' => 95]);
        } elseif ($demo === 'svg-clean') {
            $builder = $builder->writerOptions([
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
                SvgWriter::WRITER_OPTION_BLOCK_ID => 'meinQRSVG',
            ]);
        }

        if ($useLabel && class_exists(\Endroid\QrCode\Label\Alignment\LabelAlignmentCenter::class)) {
            $builder = $builder->labelAlignment(new \Endroid\QrCode\Label\Alignment\LabelAlignmentCenter());
        }

        $result = $builder
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        $data = $result->getString();

        $extension = 'png';
        if ($writer instanceof SvgWriter) {
            $extension = 'svg';
        } elseif ($writer instanceof WebPWriter) {
            $extension = 'webp';
        }

        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', $result->getMimeType())
            ->withHeader('Content-Disposition', 'inline; filename="qr.' . $extension . '"');
    }

    /**
     * Render a PDF containing the QR code.
     */
    public function pdf(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $text   = (string)($params['t'] ?? '');
        if ($text === '') {
            return $response->withStatus(400);
        }

        $fg     = (string)($params['fg'] ?? '0000ff');
        $bg     = (string)($params['bg'] ?? 'ffffff');
        $size   = (int)($params['s'] ?? 300);
        $margin = (int)($params['m'] ?? 20);

        $builder = Builder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->encoding(new Encoding('UTF-8'))
            ->size($size)
            ->margin($margin)
            ->backgroundColor($this->parseColor($bg, new Color(255, 255, 255)))
            ->foregroundColor($this->parseColor($fg, new Color(0, 0, 255)));

        $result = $builder
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        $png = $result->getString();
        $tmp = tempnam(sys_get_temp_dir(), 'qr');
        if ($tmp !== false) {
            file_put_contents($tmp, $png);
        }

        $pdf = new FPDF();
        $pdf->AddPage();

        $cfg = $this->config->getConfig();
        $title = (string)($cfg['header'] ?? '');
        $subtitle = (string)($cfg['subheader'] ?? '');
        $logoFile = __DIR__ . '/../../' . ltrim($cfg['logoPath'] ?? '', '/');
        $logoTemp = null;
        // Height of the header area in which logo, titles and QR code are placed
        $qrSize = 20.0; // mm
        $headerHeight = max(25.0, $qrSize + 10.0); // ensure QR code fits

        if (is_readable($logoFile)) {
            if (str_ends_with(strtolower($logoFile), '.webp')) {
                $img = Image::make($logoFile);
                $logoTemp = tempnam(sys_get_temp_dir(), 'logo') . '.png';
                $img->encode('png')->save($logoTemp, 80);
                $logoFile = $logoTemp;
            }
            $pdf->Image($logoFile, 10, 10, 20, 0, 'PNG');
        }

        $pdf->SetXY(10, 10);
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell($pdf->GetPageWidth() - 20, 8, $title, 0, 2, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell($pdf->GetPageWidth() - 20, 6, $subtitle, 0, 2, 'C');

        $y = 10 + $headerHeight - 2;
        $pdf->SetLineWidth(0.2);
        $pdf->Line(10, $y, $pdf->GetPageWidth() - 10, $y);

        if ($tmp !== false) {
            // Place the QR code in the upper right corner of the header
            $qrX = $pdf->GetPageWidth() - 10 - $qrSize;
            $qrY = 10.0; // top margin
            $pdf->Image($tmp, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
            unlink($tmp);
        }

        if ($logoTemp !== null) {
            unlink($logoTemp);
        }

        $pdf->SetXY(10, $y + 5);
        $invite = (string)($cfg['inviteText'] ?? '');
        if ($invite !== '') {
            $team = (string)($params['t'] ?? '');
            if ($team === '') {
                $team = 'Team';
            }
            $invite = str_ireplace('[team]', $team, $invite);
            $invite = preg_replace('/<br\s*\/>?/i', "\n", $invite);
            $invite = preg_replace('/<h[1-6]>(.*?)<\/h[1-6]>/i', "$1\n", $invite);
            $invite = preg_replace('/<p[^>]*>(.*?)<\/p>/i', "$1\n", $invite);
            $invite = strip_tags($invite);
            $invite = html_entity_decode($invite);
            $invite = $this->sanitizePdfText($invite);
            $pdf->SetFont('Arial', '', 11);
            $pdf->MultiCell($pdf->GetPageWidth() - 20, 6, $invite);
        }

        $output = $pdf->Output('S');

        $response->getBody()->write($output);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="qr.pdf"');
    }

    /**
     * Convert a UTF-8 string to ISO-8859-1 and remove unsupported characters.
     */
    private function sanitizePdfText(string $text): string
    {
        // Remove characters outside ISO-8859-1
        $text = preg_replace('/[^\x00-\xFF]/u', '', $text);
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Parse a hex color string or return the provided default.
     */
    private function parseColor(string $hex, Color $default): Color
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6 && ctype_xdigit($hex)) {
            return new Color(
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2))
            );
        }
        return $default;
    }
}
