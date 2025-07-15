<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\TeamService;
use App\Service\EventService;
use App\Service\CatalogService;
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
use App\Service\Pdf;
use Intervention\Image\ImageManagerStatic as Image;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Generates QR codes with various customization options.
 */
class QrController
{
    private ConfigService $config;
    private TeamService $teams;
    private EventService $events;
    private CatalogService $catalogs;
    /**
     * Stack for keeping track of currently selected PDF font.
     * Each entry is an array with [family, style, size].
     *
     * @var array<int, array{0:string,1:string,2:int}>
     */
    private array $fontStack = [];

    /**
     * Inject configuration service dependency.
     */
    public function __construct(ConfigService $config, TeamService $teams, EventService $events, CatalogService $catalogs)
    {
        $this->config = $config;
        $this->teams = $teams;
        $this->events = $events;
        $this->catalogs = $catalogs;
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

        $cfg = $this->config->getConfig();
        $uid = (string)($cfg['event_uid'] ?? '');
        $ev = null;
        if ($uid !== '') {
            $ev = $this->events->getByUid($uid);
        }
        if ($ev === null) {
            $ev = ['name' => '', 'description' => ''];
        }
        $title = (string)$ev['name'];
        $subtitle = (string)$ev['description'];
        $logoFile = __DIR__ . '/../../data/' . ltrim((string)($cfg['logoPath'] ?? ''), '/');

        $pdf = new Pdf($title, $subtitle, $logoFile);
        $templatePath = __DIR__ . '/../../data/template.pdf';
        $catSlug = (string)($params['catalog'] ?? '');
        if ($catSlug !== '') {
            $design = $this->catalogs->getDesignPath($catSlug);
            if ($design !== null && $design !== '') {
                $templatePath = __DIR__ . '/../../data/' . ltrim($design, '/');
            }
        }
        $pdf->AddPage();
        if (is_readable($templatePath)) {
            $pdf->setSourceFile($templatePath);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
        }

        $qrSize = 20.0; // mm

        if ($tmp !== false) {
            $qrX = $pdf->GetPageWidth() - 10 - $qrSize;
            $qrY = 10.0; // top margin
            $pdf->Image($tmp, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
            unlink($tmp);
        }

        $pdf->SetXY(10, $pdf->getBodyStartY());
        $invite = (string)($cfg['inviteText'] ?? '');
        if ($invite !== '') {
            $team = (string)($params['t'] ?? '');
            if ($team === '') {
                $team = 'Team';
            }
            $invite = str_ireplace('[team]', $team, $invite);
            $pdf->SetFont('Arial', '', 11);
            $this->renderHtml($pdf, $invite, 'Arial', '', 11);
        }

        // Draw footer separator about 1 cm from the bottom
        $footerY = $pdf->GetPageHeight() - 10; // 10 mm margin
        $pdf->SetLineWidth(0.2);
        $pdf->Line(10, $footerY, $pdf->GetPageWidth() - 10, $footerY);

        $output = $pdf->Output('S');

        $response->getBody()->write($output);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="qr.pdf"');
    }

    /**
     * Render a PDF with invitations for all teams.
     */
    public function pdfAll(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $fg     = (string)($params['fg'] ?? '0000ff');
        $bg     = (string)($params['bg'] ?? 'ffffff');
        $size   = (int)($params['s'] ?? 300);
        $margin = (int)($params['m'] ?? 20);

        $teams = $this->teams->getAll();

        $cfg = $this->config->getConfig();
        $uid = (string)($cfg['event_uid'] ?? '');
        $ev = null;
        if ($uid !== '') {
            $ev = $this->events->getByUid($uid);
        }
        if ($ev === null) {
            $ev = ['name' => '', 'description' => ''];
        }
        $title = (string)$ev['name'];
        $subtitle = (string)$ev['description'];
        $logoPath = __DIR__ . '/../../data/' . ltrim((string)($cfg['logoPath'] ?? ''), '/');

        $pdf = new Pdf($title, $subtitle, $logoPath);
        $templatePath = __DIR__ . '/../../data/template.pdf';
        $catSlug = (string)($params['catalog'] ?? '');
        if ($catSlug !== '') {
            $design = $this->catalogs->getDesignPath($catSlug);
            if ($design !== null && $design !== '') {
                $templatePath = __DIR__ . '/../../data/' . ltrim($design, '/');
            }
        }

        foreach ($teams as $team) {
            $builder = Builder::create()
                ->writer(new PngWriter())
                ->data($team)
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

            $pdf->AddPage();
            if (is_readable($templatePath)) {
                $pdf->setSourceFile($templatePath);
                $tpl = $pdf->importPage(1);
                $pdf->useTemplate($tpl, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
            }

            $qrSize = 20.0;

            if ($tmp !== false) {
                $qrX = $pdf->GetPageWidth() - 10 - $qrSize;
                $qrY = 10.0;
                $pdf->Image($tmp, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
                unlink($tmp);
            }

            $pdf->SetXY(10, $pdf->getBodyStartY());
            $invite = (string)($cfg['inviteText'] ?? '');
            if ($invite !== '') {
                $invite = str_ireplace('[team]', $team ?: 'Team', $invite);
                $pdf->SetFont('Arial', '', 11);
                $this->renderHtml($pdf, $invite, 'Arial', '', 11);
            }

            $footerY = $pdf->GetPageHeight() - 10;
            $pdf->SetLineWidth(0.2);
            $pdf->Line(10, $footerY, $pdf->GetPageWidth() - 10, $footerY);
        }

        $output = $pdf->Output('S');

        $response->getBody()->write($output);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="invites.pdf"');
    }

    /**
     * Convert a UTF-8 string to the Windows-1252 encoding used by FPDF.
     * Unsupported characters are approximated or omitted.
     */
    private function sanitizePdfText(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($converted === false) {
            // Fallback: replace any byte outside the ASCII range
            return preg_replace('/[^\x00-\x7F]/', '?', $text);
        }
        return $converted;
    }

    /**
     * Render a limited subset of HTML tags to the PDF.
     */
    private function renderHtml(
        FPDF $pdf,
        string $html,
        string $family = 'Arial',
        string $style = '',
        int $size = 11
    ): void {
        // Start the font stack with the provided base font.
        $this->fontStack = [[$family, $style, $size]];

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        // Force UTF-8 parsing to correctly handle special characters like ö,ä or ü
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->renderHtmlNode($pdf, $doc->documentElement);
    }

    private function renderHtmlNode(FPDF $pdf, \DOMNode $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $this->sanitizePdfText($child->nodeValue);
                if ($text !== '') {
                    $pdf->Write(6, $text);
                }
            } elseif ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);
                switch ($tag) {
                    case 'br':
                        $pdf->Ln(6);
                        break;
                    case 'p':
                        $this->renderHtmlNode($pdf, $child);
                        $pdf->Ln(12);
                        break;
                    case 'strong':
                    case 'b':
                        $current = end($this->fontStack);
                        $newStyle = $current[1];
                        if (strpos($newStyle, 'B') === false) {
                            $newStyle .= 'B';
                        }
                        $this->fontStack[] = [$current[0], $newStyle, $current[2]];
                        $pdf->SetFont($current[0], $newStyle, $current[2]);
                        $this->renderHtmlNode($pdf, $child);
                        array_pop($this->fontStack);
                        $pdf->SetFont($current[0], $current[1], $current[2]);
                        break;
                    case 'em':
                    case 'i':
                        $current = end($this->fontStack);
                        $newStyle = $current[1];
                        if (strpos($newStyle, 'I') === false) {
                            $newStyle .= 'I';
                        }
                        $this->fontStack[] = [$current[0], $newStyle, $current[2]];
                        $pdf->SetFont($current[0], $newStyle, $current[2]);
                        $this->renderHtmlNode($pdf, $child);
                        array_pop($this->fontStack);
                        $pdf->SetFont($current[0], $current[1], $current[2]);
                        break;
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        $current = end($this->fontStack);
                        $level = (int)substr($tag, 1);
                        $sizes = [1 => 16, 2 => 14, 3 => 12, 4 => 11, 5 => 11, 6 => 11];
                        $this->fontStack[] = [$current[0], $current[1], $current[2]];
                        $pdf->SetFont($current[0], 'B', $sizes[$level]);
                        $this->renderHtmlNode($pdf, $child);
                        // Further reduce the spacing after headings
                        $pdf->Ln(2);
                        array_pop($this->fontStack);
                        $pdf->SetFont($current[0], $current[1], $current[2]);
                        break;
                    default:
                        $this->renderHtmlNode($pdf, $child);
                        break;
                }
            }
        }
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
