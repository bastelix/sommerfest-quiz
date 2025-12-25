<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\LandingNews;
use App\Domain\NewsletterCampaign;
use App\Infrastructure\MailProviderRepository;
use App\Service\MailProvider\BrevoCampaignClient;
use RuntimeException;

/**
 * Pushes newsletter campaigns to the configured mail provider.
 */
class NewsletterCampaignSender
{
    public function __construct(
        private NewsletterCampaignService $campaigns,
        private LandingNewsService $landingNews,
        private MailProviderRepository $providers
    ) {
    }

    public function send(NewsletterCampaign $campaign, string $basePath): NewsletterCampaign
    {
        $config = $this->providers->findActive($campaign->getNamespace());
        if ($config === null) {
            throw new RuntimeException('No mail provider configured for newsletter campaigns.');
        }

        $provider = strtolower((string) ($config['provider_name'] ?? ''));
        if ($provider !== 'brevo') {
            throw new RuntimeException('Newsletter campaigns currently require the Brevo provider.');
        }

        $apiKey = (string) ($config['api_key'] ?? '');
        $listId = $campaign->getAudienceId();
        $templateId = $campaign->getTemplateId();
        if ($templateId === null || (int) $templateId <= 0) {
            throw new RuntimeException('A valid provider template ID is required to send campaigns.');
        }
        if ($listId === null || (int) $listId <= 0) {
            throw new RuntimeException('A valid provider audience or segment ID is required to send campaigns.');
        }

        $newsEntries = $this->landingNews->getByIds($campaign->getNewsIds());
        $articles = array_map(fn ($entry): array => $this->mapNewsEntry($entry, $basePath), $newsEntries);
        $client = new BrevoCampaignClient($apiKey);
        $result = $client->createAndSend(
            $campaign->getName(),
            (int) $templateId,
            (int) $listId,
            [
                'namespace' => $campaign->getNamespace(),
                'articles' => $articles,
            ]
        );

        $this->campaigns->markSent($campaign->getId(), $result['campaign_id'] ?? null, $result['message_id'] ?? null);

        return $this->campaigns->find($campaign->getId()) ?? $campaign;
    }

    private function mapNewsEntry(LandingNews $entry, string $basePath): array
    {
        $urlBase = rtrim($basePath, '/');
        $url = sprintf('%s/%s/news/%s', $urlBase, $entry->getPageSlug(), $entry->getSlug());

        return [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'excerpt' => $entry->getExcerpt(),
            'slug' => $entry->getSlug(),
            'page' => $entry->getPageSlug(),
            'url' => $url,
            'published_at' => $entry->getPublishedAt()?->format(DATE_ATOM),
        ];
    }
}
