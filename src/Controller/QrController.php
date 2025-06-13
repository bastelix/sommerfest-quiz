<?php

declare(strict_types=1);

namespace App\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
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

        if (method_exists(QrCode::class, 'create')) {
            $qrCode = QrCode::create($text);
        } else {
            $qrCode = new QrCode($text);
        }
        $writer = new PngWriter();

        if (method_exists($writer, 'write')) {
            $data = $writer->write($qrCode)->getString();
        } elseif (method_exists($writer, 'writeString')) {
            $data = $writer->writeString($qrCode);
        } else {
            $tmp = sys_get_temp_dir() . '/' . uniqid('qr_', true) . '.png';
            if (method_exists($writer, 'writeFile')) {
                $writer->writeFile($qrCode, $tmp);
            } else {
                $writer->write($qrCode)->saveToFile($tmp);
            }
            $data = file_get_contents($tmp) ?: '';
            @unlink($tmp);
        }

        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Disposition', 'inline; filename="qr.png"');
    }
}
