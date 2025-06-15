<?php

declare(strict_types=1);

namespace App\Controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
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

        $builder = Builder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->encoding(new Encoding('UTF-8'))
            ->size($size)
            ->margin($margin)
            ->backgroundColor($this->parseColor($bg, new Color(255, 255, 255)))
            ->foregroundColor($this->parseColor($fg, new Color(35, 180, 90)))
            ->labelText($text)
            ->labelFont(new NotoSans(20));

        if (class_exists(\Endroid\QrCode\Label\Alignment\LabelAlignmentCenter::class)) {
            $builder = $builder->labelAlignment(new \Endroid\QrCode\Label\Alignment\LabelAlignmentCenter());
        }

        $result = $builder
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        $data = $result->getString();

        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Disposition', 'inline; filename="qr.png"');
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
