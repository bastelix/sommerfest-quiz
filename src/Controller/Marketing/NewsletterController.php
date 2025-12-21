<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Infrastructure\Database;
use App\Service\DomainStartPageService;
use App\Service\EmailConfirmationService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MarketingNewsletterConfigService;
use App\Service\MarketingSlugResolver;
use App\Service\NamespaceResolver;
use App\Service\NewsletterSubscriptionService;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

use function is_array;
use function json_decode;
use function trim;

class NewsletterController
{
    public function confirm(Request $request, Response $response): Response
    {
        $token = trim((string) ($request->getQueryParams()['token'] ?? ''));
        if ($token === '') {
            return $this->renderStatus($request, $response, false, 'confirm');
        }

        $pdo = Database::connectFromEnv();
        $confirmationService = new EmailConfirmationService($pdo);

        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $manager = new MailProviderManager(new SettingsService($pdo));
        }

        $service = new NewsletterSubscriptionService($pdo, $confirmationService, $manager);
        $domainService = new DomainStartPageService($pdo);
        $configService = new MarketingNewsletterConfigService($pdo);
        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();

        $success = false;
        $marketingSlug = null;
        $ctas = [];
        try {
            $result = $service->confirmSubscription($token);
            $success = $result->isSuccess();
            if ($success) {
                $metadata = $result->getMetadata();
                $landingHost = isset($metadata['landing']) ? (string) $metadata['landing'] : '';
                $resolved = $landingHost !== '' ? $domainService->getStartPage($landingHost) : null;
                if ($resolved === null || $resolved === '') {
                    $resolved = 'landing';
                }

                $marketingSlug = MarketingSlugResolver::resolveBaseSlug($resolved);
                if ($marketingSlug === '') {
                    $marketingSlug = 'landing';
                }

                $ctas = $configService->getCtasForSlug($marketingSlug, $namespace);
                if ($ctas === [] && $marketingSlug !== 'landing') {
                    $ctas = $configService->getCtasForSlug('landing', $namespace);
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Newsletter confirmation failed: ' . $exception->getMessage());
            $success = false;
        }

        return $this->renderStatus($request, $response, $success, 'confirm', $marketingSlug, $ctas);
    }

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

        $pdo = Database::connectFromEnv();
        $confirmationService = new EmailConfirmationService($pdo);

        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $manager = new MailProviderManager(new SettingsService($pdo));
        }

        $service = new NewsletterSubscriptionService($pdo, $confirmationService, $manager);

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

    /**
     * @param list<array{label:string,url:string,style:string}> $ctas
     */
    private function renderStatus(
        Request $request,
        Response $response,
        bool $success,
        string $mode,
        ?string $marketingSlug = null,
        array $ctas = []
    ): Response {
        $twig = Twig::fromRequest($request);

        return $twig->render($response, 'marketing/newsletter_status.twig', [
            'success' => $success,
            'mode' => $mode,
            'marketingSlug' => $marketingSlug,
            'ctas' => $ctas,
        ]);
    }
}
