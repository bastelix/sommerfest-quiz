<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\LandingNews;
use App\Domain\Page;
use App\Service\LandingNewsService;
use App\Service\PageService;
use App\Support\BasePathHelper;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Throwable;

use function array_filter;
use function in_array;
use function is_array;
use function trim;

class LandingNewsController
{
    private LandingNewsService $news;

    private PageService $pages;

    /** @var list<string> */
    private const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    public function __construct(?LandingNewsService $news = null, ?PageService $pages = null)
    {
        $this->news = $news ?? new LandingNewsService();
        $this->pages = $pages ?? new PageService();
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $entries = $this->news->getAll();
        $params = $request->getQueryParams();
        $status = isset($params['status']) ? (string) $params['status'] : '';

        return $view->render($response, 'admin/landing_news/index.twig', [
            'entries' => $entries,
            'status' => $status,
            'csrfToken' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->renderForm($request, $response, null);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $this->extractFormData($request);

        try {
            $publishedAt = $this->parseDateTime($data['published_at']);
            $entry = $this->news->create(
                $data['page_id'],
                $data['slug'],
                $data['title'],
                $data['excerpt'],
                $data['content'],
                $publishedAt,
                $data['is_published']
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return $this->renderForm($request, $response, null, $data, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->renderForm($request, $response, null, $data, $exception->getMessage());
        }

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $location = sprintf('%s/admin/landing-news?status=created', $basePath);

        return $response->withHeader('Location', $location)->withStatus(303);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $entry = $this->resolveEntry($args);
        if ($entry === null) {
            return $response->withStatus(404);
        }

        return $this->renderForm($request, $response, $entry);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $entry = $this->resolveEntry($args);
        if ($entry === null) {
            return $response->withStatus(404);
        }

        $data = $this->extractFormData($request);

        try {
            $publishedAt = $this->parseDateTime($data['published_at']);
            $this->news->update(
                $entry->getId(),
                $data['page_id'],
                $data['slug'],
                $data['title'],
                $data['excerpt'],
                $data['content'],
                $publishedAt,
                $data['is_published']
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return $this->renderForm($request, $response, $entry, $data, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->renderForm($request, $response, $entry, $data, $exception->getMessage());
        }

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $location = sprintf('%s/admin/landing-news?status=updated', $basePath);

        return $response->withHeader('Location', $location)->withStatus(303);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $entry = $this->resolveEntry($args);
        if ($entry !== null) {
            $this->news->delete($entry->getId());
        }

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $location = sprintf('%s/admin/landing-news?status=deleted', $basePath);

        return $response->withHeader('Location', $location)->withStatus(303);
    }

    private function renderForm(
        Request $request,
        Response $response,
        ?LandingNews $entry,
        ?array $override = null,
        ?string $error = null
    ): Response {
        $view = Twig::fromRequest($request);
        $pages = $this->getLandingPages();
        $payload = [
            'entry' => $entry,
            'pages' => $pages,
            'error' => $error,
            'csrfToken' => $_SESSION['csrf_token'] ?? '',
        ];

        if ($override !== null) {
            $payload['override'] = $override;
        }

        return $view->render($response, 'admin/landing_news/form.twig', $payload);
    }

    /**
     * @return list<Page>
     */
    private function getLandingPages(): array
    {
        $pages = $this->pages->getAll();

        return array_values(array_filter(
            $pages,
            static function (Page $page): bool {
                $slug = $page->getSlug();
                if ($slug === '') {
                    return false;
                }

                return !in_array($slug, self::EXCLUDED_SLUGS, true);
            }
        ));
    }

    private function extractFormData(Request $request): array
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $pageId = isset($data['page_id']) ? (int) $data['page_id'] : 0;
        $slug = isset($data['slug']) ? (string) $data['slug'] : '';
        $title = isset($data['title']) ? (string) $data['title'] : '';
        $excerpt = isset($data['excerpt']) ? (string) $data['excerpt'] : null;
        $content = isset($data['content']) ? (string) $data['content'] : '';
        $isPublished = !empty($data['is_published']);
        $publishedAt = isset($data['published_at']) ? (string) $data['published_at'] : '';

        return [
            'page_id' => $pageId,
            'slug' => trim($slug),
            'title' => trim($title),
            'excerpt' => $excerpt !== null ? trim($excerpt) : null,
            'content' => $content,
            'is_published' => $isPublished,
            'published_at' => trim($publishedAt),
        ];
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $trimmed);
        if ($dateTime instanceof DateTimeImmutable) {
            return $dateTime;
        }

        return new DateTimeImmutable($trimmed);
    }

    private function resolveEntry(array $args): ?LandingNews
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        return $this->news->find($id);
    }
}
