<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Infrastructure\Database;
use App\Service\MailProvider\MailProviderManager;
use App\Service\NamespaceResolver;
use App\Service\NewsletterSubscriptionService;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

use function is_array;
use function json_decode;

class NewsletterController
{
    public function unsubscribe(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $data = json_decode((string) $body, true);
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            return $response->withStatus(400);
        }

        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $manager = new MailProviderManager(new SettingsService(Database::connectFromEnv()));
        }

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $service = new NewsletterSubscriptionService($manager, $namespace);

        $serverParams = $request->getServerParams();
        $metadata = [
            'ip' => isset($serverParams['REMOTE_ADDR']) ? (string) $serverParams['REMOTE_ADDR'] : null,
            'user_agent' => isset($serverParams['HTTP_USER_AGENT']) ? (string) $serverParams['HTTP_USER_AGENT'] : null,
            'source' => 'marketing-unsubscribe',
        ];

        try {
            $success = $service->unsubscribe($email, $metadata);
        } catch (RuntimeException $exception) {
            error_log('Newsletter unsubscribe failed: ' . $exception->getMessage());

            return $response->withStatus(500);
        }

        return $success ? $response->withStatus(204) : $response->withStatus(400);
    }

}
