<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\LandingNews;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\LandingNewsService;
use App\Service\NamespaceResolver;
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
use PDO;

use function array_filter;
use function http_build_query;
use function in_array;
use function is_array;
use function is_string;
use function trim;

class LandingNewsController
{
    private LandingNewsService $news;

    private PageService $pages;
    private NamespaceResolver $namespaceResolver;

    /** @var list<string> */
    private const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    public function __construct(
        ?LandingNewsService $news = null,
        ?PageService $pages = null,
        ?NamespaceResolver $namespaceResolver = null
    )
    {
        $this->news = $news ?? new LandingNewsService();
        $this->pages = $pages ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = isset($params['status']) ? (string) $params['status'] : '';
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->pages->getAllForNamespace($namespace);
        $allowedPageIds = [];
        foreach ($pages as $page) {
            $allowedPageIds[$page->getId()] = true;
        }
        $entries = array_values(array_filter(
            $this->news->getAll(),
            static fn (LandingNews $entry): bool => isset($allowedPageIds[$entry->getPageId()])
        ));

        return $view->render($response, 'admin/landing_news/index.twig', [
            'entries' => $entries,
            'status' => $this->normalizeStatus($status),
            'csrfToken' => $this->ensureCsrfToken(),
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'available_namespaces' => $availableNamespaces,
            'pageTab' => 'landing-news',
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->renderForm($request, $response, null);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $this->extractFormData($request);
        if (!$this->pageMatchesNamespace($request, $data['page_id'])) {
            return $this->renderForm(
                $request,
                $response,
                null,
                $data,
                'Die ausgewählte Seite ist im Namespace nicht verfügbar.'
            );
        }

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

        return $this->redirectToPages($request, $response, 'created');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $entry = $this->resolveEntry($request, $args);
        if ($entry === null) {
            return $response->withStatus(404);
        }

        return $this->renderForm($request, $response, $entry);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $entry = $this->resolveEntry($request, $args);
        if ($entry === null) {
            return $response->withStatus(404);
        }

        $data = $this->extractFormData($request);
        if (!$this->pageMatchesNamespace($request, $data['page_id'])) {
            return $this->renderForm(
                $request,
                $response,
                $entry,
                $data,
                'Die ausgewählte Seite ist im Namespace nicht verfügbar.'
            );
        }

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

        return $this->redirectToPages($request, $response, 'updated');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $entry = $this->resolveEntry($request, $args);
        if ($entry !== null) {
            $this->news->delete($entry->getId());
        }

        return $this->redirectToPages($request, $response, 'deleted');
    }

    private function renderForm(
        Request $request,
        Response $response,
        ?LandingNews $entry,
        ?array $override = null,
        ?string $error = null
    ): Response {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $pages = $this->getLandingPages($namespace);
        $payload = [
            'entry' => $entry,
            'pages' => $pages,
            'error' => $error,
            'csrfToken' => $this->ensureCsrfToken(),
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'pageNamespace' => $namespace,
            'available_namespaces' => $availableNamespaces,
            'pageTab' => 'landing-news',
        ];

        if ($override !== null) {
            $payload['override'] = $override;
        }

        return $view->render($response, 'admin/landing_news/form.twig', $payload);
    }

    private function redirectToPages(
        Request $request,
        Response $response,
        ?string $status = null,
        int $httpStatus = 303
    ): Response {
        $location = $this->buildPagesLocation($request, $status);

        return $response->withHeader('Location', $location)->withStatus($httpStatus);
    }

    private function buildPagesLocation(Request $request, ?string $status = null): string
    {
        $normalizedStatus = $this->normalizeStatus($status);
        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $params = $request->getQueryParams();
        $query = [];
        $event = $params['event'] ?? null;
        if (is_string($event) && $event !== '') {
            $query['event'] = (string) $event;
        }
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if ($namespace !== '') {
            $query['namespace'] = $namespace;
        }
        if ($normalizedStatus !== '') {
            $query['status'] = $normalizedStatus;
        }

        $queryString = http_build_query($query);

        return $basePath . '/admin/landing-news' . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function normalizeStatus(?string $status): string
    {
        if ($status === null) {
            return '';
        }

        $value = trim($status);
        $allowed = ['created', 'updated', 'deleted'];

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function ensureCsrfToken(): string
    {
        $token = $_SESSION['csrf_token'] ?? '';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }

    /**
     * @return list<Page>
     */
    private function getLandingPages(string $namespace): array
    {
        $pages = $this->pages->getAllForNamespace($namespace);

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

    private function pageMatchesNamespace(Request $request, int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return false;
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();

        return $page->getNamespace() === $namespace;
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request): array
    {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $repository = new NamespaceRepository($pdo);
        try {
            $availableNamespaces = $repository->list();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if (!array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
        )) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        if (!array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        )) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [$availableNamespaces, $namespace];
    }

    private function resolveEntry(Request $request, array $args): ?LandingNews
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        $entry = $this->news->find($id);
        if ($entry === null) {
            return null;
        }

        $page = $this->pages->findById($entry->getPageId());
        if ($page === null) {
            return null;
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if ($page->getNamespace() !== $namespace) {
            return null;
        }

        return $entry;
    }
}
