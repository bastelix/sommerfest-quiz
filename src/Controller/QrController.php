<?php

declare(strict_types=1);

namespace App\Controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeMode;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class QrController
{
    public function image(Request $request, Response $response): Response
    {
        $text = (string)($request->getQueryParams()['t'] ?? '');
        if ($text === '') {
            return $response->withStatus(400);
        }

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->encoding(new Encoding('UTF-8'))
            ->size(300)
            ->margin(20)
            ->backgroundColor(new Color(255, 255, 255))
            ->foregroundColor(new Color(35, 180, 90))
            ->labelText($text)
            ->labelFont(new NotoSans(20))
            ->labelAlignment(new LabelAlignmentCenter())
            ->roundBlockSizeMode(RoundBlockSizeMode::ENLARGE)
            ->build();

        $data = $result->getString();

        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Disposition', 'inline; filename="qr.png"');
    }
}
