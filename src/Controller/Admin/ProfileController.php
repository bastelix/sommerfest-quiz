<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\TenantService;
use App\Infrastructure\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProfileController
{
    public function update(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $fields = [
            'plan' => (string) ($data['plan'] ?? ''),
            'billing_info' => (string) ($data['billing_info'] ?? ''),
            'imprint_name' => (string) ($data['imprint_name'] ?? ''),
            'imprint_street' => (string) ($data['imprint_street'] ?? ''),
            'imprint_zip' => (string) ($data['imprint_zip'] ?? ''),
            'imprint_city' => (string) ($data['imprint_city'] ?? ''),
            'imprint_email' => (string) ($data['imprint_email'] ?? ''),
        ];

        $domainType = $request->getAttribute('domainType');
        if ($domainType === 'main') {
            $path = dirname(__DIR__, 3) . '/data/profile.json';
            $current = [];
            if (is_file($path)) {
                $current = json_decode((string) file_get_contents($path), true) ?? [];
            }
            foreach ($fields as $k => $v) {
                $current[$k] = $v;
            }
            file_put_contents($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $pdo = $request->getAttribute('pdo');
            if (!$pdo instanceof PDO) {
                $pdo = Database::connectFromEnv();
            }
            $host = $request->getUri()->getHost();
            $sub = explode('.', $host)[0];
            $service = new TenantService($pdo);
            $service->updateProfile($sub, $fields);
        }

        return $response->withStatus(204);
    }
}
