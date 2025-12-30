<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\Database;
use App\Service\NamespaceResolver;
use App\Service\PageBlockContractMigrator;
use App\Service\PageService;
use App\Service\AuditLogger;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use LogicException;
use RuntimeException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

class PageController
{
    private PageService $pageService;
    private NamespaceResolver $namespaceResolver;
    private PageBlockContractMigrator $blockMigrator;
    private AuditLogger $audit;

    /** @var array<string, string[]> */
    private array $editableSlugs = [];

    private const LEGACY_VARIANT_NORMALIZATION = [
        'hero' => [
            'media_right' => 'media-right',
            'centered_cta' => 'centered-cta',
        ],
        'process_steps' => [
            'timeline_vertical' => 'numbered-vertical',
        ],
    ];

    public function __construct(
        ?PageService $pageService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?PageBlockContractMigrator $blockMigrator = null,
        ?AuditLogger $audit = null
    ) {
        $this->pageService = $pageService ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->blockMigrator = $blockMigrator ?? new PageBlockContractMigrator($this->pageService);
        $this->audit = $audit ?? new AuditLogger(Database::connectFromEnv());
    }

    /**
     * Display the edit form for a static page.
     */
    public function edit(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $content = $this->pageService->getByKey($namespace, (string) $slug);
        if ($content === null) {
            return $response->withStatus(404);
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/pages/edit.twig', [
            'slug' => $slug,
            'content' => $content,
        ]);
    }

    /**
     * Persist new HTML for a static page.
     */
    public function update(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $html = (string)($data['content'] ?? '');
        $this->pageService->save($namespace, (string) $slug, $html);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        if ($this->pageService->findByKey($namespace, (string) $slug) === null) {
            return $response->withStatus(404);
        }

        $this->pageService->deleteTree($namespace, (string) $slug);
        unset($this->editableSlugs[$namespace]);

        return $response->withStatus(204);
    }

    public function create(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $slug = isset($data['slug']) ? (string) $data['slug'] : '';
        $title = isset($data['title']) ? (string) $data['title'] : '';
        $content = isset($data['content']) ? (string) $data['content'] : '';
        if ($content !== '' && !mb_check_encoding($content, 'UTF-8')) {
            $namespaceForLog = $this->namespaceResolver->resolve($request)->getNamespace();
            error_log(sprintf(
                'Invalid UTF-8 content detected for page "%s" in namespace "%s"; normalizing input.',
                $slug,
                $namespaceForLog
            ));
        }
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();

        try {
            $page = $this->pageService->create($namespace, $slug, $title, $content);
        } catch (InvalidArgumentException $exception) {
            return $this->createJsonResponse($response, ['error' => $exception->getMessage()], 422);
        } catch (LogicException $exception) {
            return $this->createJsonResponse($response, ['error' => $exception->getMessage()], 409);
        } catch (RuntimeException $exception) {
            return $this->createJsonResponse($response, ['error' => $exception->getMessage()], 500);
        }

        return $this->createJsonResponse($response, ['page' => $page], 201);
    }

    public function copy(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $data = $this->parseRequestData($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $targetNamespace = (string) ($data['targetNamespace'] ?? $data['target_namespace'] ?? $data['namespace'] ?? '');

        try {
            $result = $this->pageService->copy($namespace, (string) $slug, $targetNamespace);
        } catch (InvalidArgumentException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (LogicException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $this->editableSlugs = [];
        $payload = [
            'page' => $result['page'],
            'copied' => $result['copied'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    public function move(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $data = $this->parseRequestData($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $targetNamespace = (string) ($data['targetNamespace'] ?? $data['target_namespace'] ?? $data['namespace'] ?? '');

        try {
            $result = $this->pageService->move($namespace, (string) $slug, $targetNamespace);
        } catch (InvalidArgumentException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (LogicException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $this->editableSlugs = [];
        $payload = [
            'page' => $result['page'],
            'moved' => $result['moved'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function export(Request $request, Response $response, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $page = $this->pageService->findByKey($namespace, $slug);
        if ($page === null) {
            return $response->withStatus(404);
        }

        $decoded = json_decode($page->getContent(), true);
        if (!is_array($decoded) || !isset($decoded['blocks'])) {
            return $this->createJsonResponse($response, ['error' => 'Page content is not valid block JSON.'], 422);
        }

        if (!$this->blockMigrator->isContractValid($decoded)) {
            return $this->createJsonResponse(
                $response,
                ['error' => 'Page content does not comply with the block contract.'],
                422
            );
        }

        $payload = [
            'meta' => [
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'exportedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
                'schemaVersion' => PageBlockContractMigrator::MIGRATION_VERSION,
            ],
            'blocks' => $decoded['blocks'],
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $filename = sprintf('content/%s/%s.page.json', $page->getNamespace(), $page->getSlug());

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function import(Request $request, Response $response, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $page = $this->pageService->findByKey($namespace, $slug);
        if ($page === null) {
            return $response->withStatus(404);
        }

        [$payload, $uploadedName] = $this->extractImportPayload($request);
        if ($payload === null) {
            return $this->createJsonResponse($response, ['error' => 'Invalid JSON payload.'], 400);
        }

        $meta = $payload['meta'] ?? [];
        if (!is_array($meta)) {
            return $this->createJsonResponse($response, ['error' => 'Missing meta information.'], 422);
        }

        $schemaVersion = (string) ($meta['schemaVersion'] ?? '');
        if ($schemaVersion !== PageBlockContractMigrator::MIGRATION_VERSION) {
            return $this->createJsonResponse(
                $response,
                ['error' => 'Incompatible schemaVersion in import file.'],
                422
            );
        }

        if (($meta['slug'] ?? '') !== $slug) {
            return $this->createJsonResponse($response, ['error' => 'Slug does not match target page.'], 422);
        }

        $blocks = $payload['blocks'] ?? null;
        if (!is_array($blocks)) {
            return $this->createJsonResponse($response, ['error' => 'Missing blocks array.'], 422);
        }

        $blocks = $this->normalizeImportedBlocks($blocks);

        $content = [
            'meta' => array_merge(
                is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
                [
                    'namespace' => $namespace,
                    'slug' => $slug,
                    'title' => $page->getTitle(),
                    'schemaVersion' => $schemaVersion,
                ]
            ),
            'blocks' => $blocks,
        ];

        if (!$this->blockMigrator->isContractValid($content)) {
            return $this->createJsonResponse(
                $response,
                ['error' => 'Block validation failed for imported content.'],
                422
            );
        }

        $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return $this->createJsonResponse($response, ['error' => 'Failed to encode imported content.'], 500);
        }

        $this->pageService->save($namespace, $slug, $encoded);

        $this->audit->log('page_import', [
            'pageId' => $page->getId(),
            'namespace' => $namespace,
            'slug' => $slug,
            'userId' => $_SESSION['user']['id'] ?? null,
            'username' => $_SESSION['user']['username'] ?? null,
            'sourceFile' => $uploadedName,
            'importedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->createJsonResponse($response, ['content' => $content], 200);
    }

    /**
     * @param array<int|string, mixed> $blocks
     *
     * @return array<int|string, mixed>
     */
    private function normalizeImportedBlocks(array $blocks): array
    {
        $normalized = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = is_string($block['type'] ?? null) ? $block['type'] : null;
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : null;

            if ($type !== null && $variant !== null) {
                $variant = $this->normalizeLegacyVariant($type, $variant);
                $block['variant'] = $variant;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : null;

            if ($type === 'hero' && $data !== null) {
                $block['data'] = $this->normalizeImportedHeroData($data);
            }

            $block['id'] = $this->normalizeImportedBlockId($block['id'] ?? null);

            if ($type === null || $variant === null || !$this->blockMigrator->isBlockVariantSupported($type, $variant)) {
                $normalized[] = $this->buildImportErrorBlock($type, $variant);

                continue;
            }

            $normalized[] = $block;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeImportedHeroData(array $data): array
    {
        $cta = $data['cta'] ?? null;
        if (!is_array($cta)) {
            return $data;
        }

        if (isset($cta['primary']) && is_array($cta['primary'])) {
            $primary = $cta['primary'];
            $flattened = [
                'label' => $primary['label'] ?? null,
                'href' => $primary['href'] ?? null,
            ];

            if (array_key_exists('ariaLabel', $primary)) {
                $flattened['ariaLabel'] = $primary['ariaLabel'];
            }

            $cta = $flattened;
        }

        unset($cta['secondary']);

        $data['cta'] = $cta;

        return $data;
    }

    private function normalizeLegacyVariant(string $type, string $variant): string
    {
        return self::LEGACY_VARIANT_NORMALIZATION[$type][$variant] ?? $variant;
    }

    private function normalizeImportedBlockId($id): string
    {
        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        return sprintf('imported-%s', bin2hex(random_bytes(8)));
    }

    private function buildImportErrorBlock(?string $originalType, ?string $originalVariant): array
    {
        return [
            'id' => sprintf('import-error-%s', bin2hex(random_bytes(8))),
            'type' => 'info_media',
            'variant' => 'stacked',
            'data' => [
                'body' => '⚠ This section could not be imported due to an invalid block type or variant.',
            ],
            'meta' => [
                'importError' => true,
                'originalType' => $originalType,
                'originalVariant' => $originalVariant,
            ],
        ];
    }

    public function updateNamespace(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $data = $this->parseRequestData($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $targetNamespace = (string) ($data['targetNamespace'] ?? $data['target_namespace'] ?? $data['namespace'] ?? '');

        try {
            $result = $this->pageService->updateNamespace($namespace, (string) $slug, $targetNamespace);
        } catch (InvalidArgumentException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (LogicException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $this->editableSlugs = [];
        $payload = [
            'page' => $result['page'],
            'moved' => $result['moved'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * Return the full page tree for admin UI use.
     */
    public function tree(Request $request, Response $response): Response {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $tree = array_values(array_filter(
            $this->pageService->getTree(),
            static fn (array $section): bool => ($section['namespace'] ?? '') === $namespace
        ));
        $payload = [
            'tree' => $tree,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Determine which page slugs can be edited via the admin area.
     *
     * @return string[]
     */
    private function getEditableSlugs(string $namespace): array {
        if (isset($this->editableSlugs[$namespace])) {
            return $this->editableSlugs[$namespace];
        }

        $slugs = [];
        foreach ($this->pageService->getAll() as $page) {
            if ($page->getNamespace() !== $namespace) {
                continue;
            }
            $slug = $page->getSlug();
            if ($slug === '') {
                continue;
            }
            $slugs[$slug] = true;
        }

        $this->editableSlugs[$namespace] = array_keys($slugs);

        return $this->editableSlugs[$namespace];
    }

    private function parseRequestData(Request $request): ?array
    {
        $data = $request->getParsedBody();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
        }

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function createJsonResponse(Response $response, array $payload, int $status): Response
    {
        try {
            $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            error_log(sprintf('Failed to encode JSON response: %s', $exception->getMessage()));

            $response->getBody()->write('{"error":"Failed to encode JSON response."}');

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * @return array{0: ?array, 1: ?string}
     */
    private function extractImportPayload(Request $request): array
    {
        $files = $request->getUploadedFiles();
        $upload = $this->findFirstUpload($files);
        $uploadedName = $upload?->getClientFilename();
        $raw = null;
        if ($upload !== null && $upload->getError() === UPLOAD_ERR_OK) {
            $raw = (string) $upload->getStream();
        }

        if ($raw === null || $raw === '') {
            $raw = (string) $request->getBody();
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [null, $uploadedName];
        }

        return [$payload, $uploadedName];
    }

    /**
     * @param array<string, UploadedFileInterface|array|UploadedFileInterface[]> $files
     */
    private function findFirstUpload(array $files): ?UploadedFileInterface
    {
        foreach (['file', 'page', 'pageJson', 'upload'] as $key) {
            $candidate = $files[$key] ?? null;
            if ($candidate === null) {
                continue;
            }
            if ($candidate instanceof UploadedFileInterface) {
                return $candidate;
            }
            foreach ($candidate as $upload) {
                return $upload;
            }
        }

        return null;
    }
}
