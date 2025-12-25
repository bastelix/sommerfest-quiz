<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Infrastructure\MailProviderRepository;
use App\Service\LandingNewsService;
use App\Service\NewsletterCampaignSender;
use App\Service\NewsletterCampaignService;
use App\Service\PageService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

class NewsletterCampaignController
{
    private NewsletterCampaignService $campaigns;

    private LandingNewsService $landingNews;

    private MailProviderRepository $providers;

    public function __construct(?NewsletterCampaignService $campaigns = null, ?LandingNewsService $landingNews = null, ?MailProviderRepository $providers = null)
    {
        $pdo = Database::connectFromEnv();
        $this->campaigns = $campaigns ?? new NewsletterCampaignService($pdo);
        $this->landingNews = $landingNews ?? new LandingNewsService($pdo);
        $this->providers = $providers ?? new MailProviderRepository($pdo);
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $namespace = $request->getAttribute('pageNamespace') ?? PageService::DEFAULT_NAMESPACE;
        $campaigns = $this->campaigns->getAll((string) $namespace);
        $queryParams = $request->getQueryParams();
        $editId = isset($queryParams['edit']) ? (int) $queryParams['edit'] : null;
        $activeCampaign = $editId !== null ? $this->campaigns->find((int) $editId) : null;
        $newsEntries = $this->landingNews->getAllForNamespace((string) $namespace);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        return $view->render($response, 'admin/newsletter_campaigns.twig', [
            'pageTab' => 'newsletter-campaigns',
            'campaigns' => $campaigns,
            'activeCampaign' => $activeCampaign,
            'newsEntries' => $newsEntries,
            'pageNamespace' => $namespace,
            'csrf_token' => $csrf,
            'error_message' => isset($queryParams['error']) ? (string) $queryParams['error'] : null,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $namespace = (string) ($request->getAttribute('pageNamespace') ?? PageService::DEFAULT_NAMESPACE);
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $name = (string) ($data['name'] ?? '');
        $newsIds = isset($data['news_ids']) ? (array) $data['news_ids'] : [];
        $templateId = isset($data['template_id']) ? (string) $data['template_id'] : null;
        $audienceId = isset($data['audience_id']) ? (string) $data['audience_id'] : null;
        $status = (string) ($data['status'] ?? 'draft');
        $scheduledRaw = isset($data['scheduled_for']) ? (string) $data['scheduled_for'] : '';
        $scheduledFor = $scheduledRaw !== '' ? new DateTimeImmutable($scheduledRaw) : null;

        if ($id === null) {
            $this->campaigns->create($namespace, $name, $newsIds, $templateId, $audienceId, $status, $scheduledFor);
        } else {
            $this->campaigns->update($id, $namespace, $name, $newsIds, $templateId, $audienceId, $status, $scheduledFor);
        }

        $query = $namespace !== '' ? '?namespace=' . rawurlencode($namespace) : '';

        return $response->withHeader('Location', $request->getAttribute('basePath') . '/admin/newsletter-campaigns' . $query)
            ->withStatus(302);
    }

    public function send(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $namespace = (string) ($request->getAttribute('pageNamespace') ?? PageService::DEFAULT_NAMESPACE);
        $campaignId = isset($args['id']) ? (int) $args['id'] : 0;
        $campaign = $this->campaigns->find($campaignId);
        if ($campaign === null || $campaign->getNamespace() !== $namespace) {
            throw new RuntimeException('Campaign not found.');
        }

        $sender = new NewsletterCampaignSender($this->campaigns, $this->landingNews, $this->providers);
        $basePath = (string) ($request->getAttribute('basePath') ?? '');
        $query = $namespace !== '' ? '?namespace=' . rawurlencode($namespace) : '';
        $baseLocation = (string) $request->getAttribute('basePath') . '/admin/newsletter-campaigns' . $query;

        try {
            $sender->send($campaign, $basePath);
        } catch (RuntimeException $exception) {
            $this->campaigns->markFailed($campaign->getId());
            $separator = str_contains($baseLocation, '?') ? '&' : '?';

            return $response
                ->withHeader('Location', $baseLocation . $separator . 'error=' . rawurlencode($exception->getMessage()))
                ->withStatus(302);
        }

        return $response->withHeader('Location', $baseLocation)
            ->withStatus(302);
    }
}
