<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\TeamService;
use App\Service\EventService;
use App\Service\CatalogService;
use App\Service\QrCodeService;
use App\Service\ResultService;
use App\Service\Pdf;
use App\Service\UrlService;
use App\Support\HttpCacheHelper;
use FPDF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

use function array_flip;
use function array_intersect_key;
use function crc32;
use function hash;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function json_encode;
use function ksort;
use function sprintf;

use const JSON_UNESCAPED_SLASHES;

/**
 * Generates QR codes with various customization options.
 */
class QrController
{
    private const QR_CACHE_CONTROL = 'public, max-age=31536000, immutable';
    private const QR_CONFIG_KEYS = [
        'qrColorTeam',
        'qrColorCatalog',
        'qrColorEvent',
        'qrLabelLine1',
        'qrLabelLine2',
        'qrLogoPath',
        'qrLogoWidth',
        'qrLogoPunchout',
    ];

    private ConfigService $config;
    private TeamService $teams;
    private EventService $events;
    private CatalogService $catalogs;
    private QrCodeService $qrService;
    private ResultService $results;
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
    public function __construct(
        ConfigService $config,
        TeamService $teams,
        EventService $events,
        CatalogService $catalogs,
        QrCodeService $qrService,
        ResultService $results
    ) {
        $this->config = $config;
        $this->teams = $teams;
        $this->events = $events;
        $this->catalogs = $catalogs;
        $this->qrService = $qrService;
        $this->results = $results;
    }

    /**
     * Generate a catalog QR code with default styling.
     */
    public function catalog(Request $request, Response $response): Response {
        $cfg = $this->config->getConfig();
        try {
            $out = $this->qrService->generateCatalog($request->getQueryParams(), $cfg);
        } catch (Throwable $e) {
            error_log('Catalog QR generation failed: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            return $this->errorResponse($response);
        }
        $response->getBody()->write($out['body']);
        $response = $response
            ->withHeader('Content-Type', $out['mime'])
            ->withStatus(200);

        $context = [
            'type' => 'catalog',
            'params' => $request->getQueryParams(),
            'config' => $this->filterQrConfig($cfg),
        ];

        return $this->applyQrCaching($request, $response, $context);
    }

    /**
     * Generate a team QR code with default styling using QrCodeService defaults.
     */
    public function team(Request $request, Response $response): Response {
        $cfg = $this->config->getConfig();
        try {
            $out = $this->qrService->generateTeam($request->getQueryParams(), $cfg);
        } catch (Throwable $e) {
            error_log('Team QR generation failed: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            return $this->errorResponse($response);
        }
        $response->getBody()->write($out['body']);
        $response = $response
            ->withHeader('Content-Type', $out['mime'])
            ->withStatus(200);

        $context = [
            'type' => 'team',
            'params' => $request->getQueryParams(),
            'config' => $this->filterQrConfig($cfg),
        ];

        return $this->applyQrCaching($request, $response, $context);
    }

    /**
     * Generate an event QR code with default styling.
     */
    public function event(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $event = (string)($params['event'] ?? '');
        if ($event === '') {
            $response->getBody()->write('missing event uid');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        if (($params['t'] ?? '') === '') {
            $params['t'] = '?event=' . $event;
        }
        $cfg = $this->config->getConfigForEvent($event);
        try {
            $out = $this->qrService->generateEvent($params, $cfg);
        } catch (Throwable $e) {
            error_log('Event QR generation failed: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            return $this->errorResponse($response);
        }
        $response->getBody()->write($out['body']);
        $response = $response
            ->withHeader('Content-Type', $out['mime'])
            ->withStatus(200);

        $context = [
            'type' => 'event',
            'params' => $params,
            'config' => $this->filterQrConfig($cfg),
        ];

        return $this->applyQrCaching($request, $response, $context);
    }

    /**
     * Render a QR code image based on query parameters.
     */
    public function image(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $text = (string)($params['t'] ?? '?');
        if ($text === '') {
            $text = '?';
        }

        $format = strtolower((string)($params['format'] ?? 'png'));
        if (!in_array($format, ['png', 'svg'], true)) {
            $format = 'png';
        }

        $options = [
            'fg' => $params['fg'] ?? null,
            'bg' => $params['bg'] ?? null,
        ];

        try {
            $result = $this->qrService->generateQrCode($text, $format, $options);
        } catch (Throwable $e) {
            return $this->errorResponse($response);
        }

        $response->getBody()->write($result['body']);

        $mime = $result['mime'];
        $extension = str_contains($mime, 'svg') ? 'svg' : 'png';

        $response = $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'inline; filename="qr.' . $extension . '"');

        $context = [
            'type' => 'image',
            'text' => $text,
            'format' => $format,
            'options' => $options,
        ];

        return $this->applyQrCaching($request, $response, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyQrCaching(Request $request, Response $response, array $context): Response
    {
        $hash = $this->createVersionHash($context);

        return HttpCacheHelper::apply(
            $request,
            $response,
            self::QR_CACHE_CONTROL,
            '"' . $hash . '"',
            $this->hashToTimestamp($hash)
        );
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function filterQrConfig(array $config): array
    {
        return array_intersect_key($config, array_flip(self::QR_CONFIG_KEYS));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createVersionHash(array $context): string
    {
        $normalized = $this->normalizeForHash($context);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '';
        }

        return hash('sha256', $json);
    }

    private function hashToTimestamp(string $hash): int
    {
        $crc = crc32($hash);
        $timestamp = (int) sprintf('%u', $crc);
        if ($timestamp === 0) {
            $timestamp = 1;
        }

        return $timestamp;
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            ksort($value);
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalizeForHash($item);
            }

            return $normalized;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * Render a PDF containing the QR code.
     */
    public function pdf(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $team   = (string)($params['t'] ?? '');
        if ($team === '') {
            return $response->withStatus(400);
        }

        $uid = (string)($params['event'] ?? '');
        if ($uid === '') {
            $response->getBody()->write('missing event uid');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $cfg = $this->config->getConfigForEvent($uid);
        $ev = $this->events->getByUid($uid);
        if ($ev === null) {
            $response->getBody()->write('unknown event uid');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain');
        }

        $qrParams = $params;
        $qrParams['format'] = 'png';
        $baseUrl = UrlService::determineBaseUrl($request);
        if (!str_contains($team, '?')) {
            $qrParams['t'] = $baseUrl . '/?event=' . rawurlencode($uid) . '&t=' . rawurlencode($team);
        }

        try {
            $out = $this->qrService->generateTeam($qrParams, $cfg);
        } catch (Throwable $e) {
            return $this->errorResponse($response);
        }

        $png = $out['body'];
        $tmp = tempnam(sys_get_temp_dir(), 'qr');
        if ($tmp !== false) {
            file_put_contents($tmp, $png);
        }
        $title = (string)$ev['name'];
        $subtitle = (string)($ev['description'] ?? '');
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
            $invite = ConfigService::sanitizeHtml($invite);
            $team = (string)($params['t'] ?? '');
            if ($team === '') {
                $team = 'Team';
            }
            $invite = str_ireplace('[team]', $team, $invite);
            $invite = str_ireplace('[event_name]', (string)$ev['name'], $invite);
            $invite = str_ireplace('[event_start]', (string)($ev['start_date'] ?? ''), $invite);
            $invite = str_ireplace('[event_end]', (string)($ev['end_date'] ?? ''), $invite);
            $invite = str_ireplace('[event_description]', (string)($ev['description'] ?? ''), $invite);
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
    public function pdfAll(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $teams = $this->teams->getAll();
        if ($teams === []) {
            $rows = $this->results->getAll();
            $names = [];
            foreach ($rows as $row) {
                $name = (string)($row['name'] ?? '');
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
            $teams = array_keys($names);
        }
        if ($teams === []) {
            $response->getBody()->write('no teams available');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain');
        }

        $uid = (string)($params['event'] ?? '');
        if ($uid === '') {
            $response->getBody()->write('missing event uid');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        $cfg = $this->config->getConfigForEvent($uid);
        $ev = $this->events->getByUid($uid);
        if ($ev === null) {
            $response->getBody()->write('unknown event uid');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain');
        }
        $title = (string)$ev['name'];
        $subtitle = (string)($ev['description'] ?? '');
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

        $baseUrl = UrlService::determineBaseUrl($request);
        foreach ($teams as $team) {
            $q = $params;
            $q['t'] = $baseUrl . '/?event=' . rawurlencode($uid) . '&t=' . rawurlencode($team);
            $q['format'] = 'png';
            try {
                $out = $this->qrService->generateTeam($q, $cfg);
            } catch (Throwable $e) {
                continue;
            }

            $png = $out['body'];
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
                $invite = ConfigService::sanitizeHtml($invite);
                $invite = str_ireplace('[team]', $team ?: 'Team', $invite);
                $invite = str_ireplace('[event_name]', (string)$ev['name'], $invite);
                $invite = str_ireplace('[event_start]', (string)($ev['start_date'] ?? ''), $invite);
                $invite = str_ireplace('[event_end]', (string)($ev['end_date'] ?? ''), $invite);
                $invite = str_ireplace('[event_description]', (string)($ev['description'] ?? ''), $invite);
                $pdf->SetFont('Arial', '', 11);
                $this->renderHtml($pdf, $invite, 'Arial', '', 11);
            }

            // Draw footer separator about 1 cm from the bottom
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

    private function errorResponse(Response $response): Response {
        $response->getBody()->write('Failed to generate QR code');
        return $response->withHeader('Content-Type', 'text/plain')->withStatus(500);
    }

    /**
     * Convert a UTF-8 string to the Windows-1252 encoding used by FPDF.
     * Unsupported characters are approximated or omitted.
     */
    private function sanitizePdfText(string $text): string {
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

    private function renderHtmlNode(FPDF $pdf, \DOMNode $node): void {
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
}
