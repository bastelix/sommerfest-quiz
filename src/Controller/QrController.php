<?php

declare(strict_types=1);

namespace App\Controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WebPWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class QrController
{
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
            ->foregroundColor($this->parseColor($fg, new Color(35, 180, 90)))
            ->labelText($text)
            ->labelFont(new NotoSans(20));

        if ($demo === 'logo') {
            $builder = $builder
                ->logoPath(__DIR__ . '/../../public/favicon.svg')
                ->logoResizeToWidth(60)
                ->logoPunchoutBackground(true);
        } elseif ($demo === 'label') {
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

        if (class_exists(\Endroid\QrCode\Label\Alignment\LabelAlignmentCenter::class)) {
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
