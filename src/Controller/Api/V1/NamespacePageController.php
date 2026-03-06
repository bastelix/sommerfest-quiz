<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Application\Middleware\ApiTokenAuthMiddleware;
use App\Application\Seo\PageSeoConfigService;
use App\Domain\PageSeoConfig;
use App\Service\CmsMenuDefinitionService;
use App\Service\PageBlockContractMigrator;
use App\Service\PageService;
use App\Support\RequestDatabase;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NamespacePageController
{
    public const SCOPE_CMS_WRITE = 'cms:write';

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?PageService $pages = null,
        private readonly ?PageBlockContractMigrator $contract = null,
        private readonly ?PageSeoConfigService $seo = null,
        private readonly ?CmsMenuDefinitionService $menus = null,
    ) {
    }

    /**
     * PUT /api/v1/namespaces/{ns}/pages/{slug}
     *
     * Body JSON:
     * {
     *   "blocks": [ ... ],
     *   "meta": { ... },
     *   "seo": { ... },
     *   "menuAssignments": [ {"slot":"header","menuId":123,"locale":"de","isActive":true,"pageScoped":true} ]
     * }
     */
    public function upsert(Request $request, Response $response, array $args): Response
    {
        $ns = isset($args['ns']) ? (string) $args['ns'] : '';
        $slug = isset($args['slug']) ? (string) $args['slug'] : '';

        $tokenNs = (string) $request->getAttribute(ApiTokenAuthMiddleware::ATTR_TOKEN_NAMESPACE);
        if ($tokenNs === '' || $ns === '' || $ns !== $tokenNs) {
            return $this->json($response, ['error' => 'namespace_mismatch'], 403);
        }

        $pdo = $this->pdo;
        if (!$pdo instanceof PDO) {
            $pdo = RequestDatabase::resolve($request);
        }

        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->json($response, ['error' => 'invalid_json'], 400);
        }

        $content = [
            'id' => $payload['id'] ?? null,
            'blocks' => $payload['blocks'] ?? null,
            'meta' => $payload['meta'] ?? [],
        ];

        if (!is_array($content['blocks'])) {
            return $this->json($response, ['error' => 'invalid_blocks'], 422);
        }
        if (!is_array($content['meta'])) {
            $content['meta'] = [];
        }

        $pages = $this->pages ?? new PageService($pdo);
        $contract = $this->contract ?? new PageBlockContractMigrator($pages);
        if (!$contract->isContractValid($content)) {
            return $this->json($response, ['error' => 'block_contract_invalid'], 422);
        }

        // Upsert page
        $existing = $pages->findByKey($ns, $slug);
        $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($contentJson === false) {
            return $this->json($response, ['error' => 'encode_failed'], 500);
        }

        if ($existing === null) {
            // Title is required for create(). API is blocks-first: use slug as safe default.
            $pages->create($ns, $slug, $slug, $contentJson, 'api:v1');
        } else {
            $pages->save($ns, $slug, $contentJson);
        }

        $page = $pages->findByKey($ns, $slug);
        if ($page === null) {
            return $this->json($response, ['error' => 'page_not_found_after_upsert'], 500);
        }

        $pageId = $page->getId();

        // Optional: SEO
        if (array_key_exists('seo', $payload) && is_array($payload['seo'])) {
            $seoPayload = $payload['seo'];
            $seo = $this->seo ?? new PageSeoConfigService($pdo);

            $cfg = new PageSeoConfig(
                $pageId,
                is_string($seoPayload['slug'] ?? null) ? (string) $seoPayload['slug'] : $slug,
                isset($seoPayload['metaTitle']) && is_string($seoPayload['metaTitle']) ? $seoPayload['metaTitle'] : null,
                isset($seoPayload['metaDescription']) && is_string($seoPayload['metaDescription']) ? $seoPayload['metaDescription'] : null,
                isset($seoPayload['canonicalUrl']) && is_string($seoPayload['canonicalUrl']) ? $seoPayload['canonicalUrl'] : null,
                isset($seoPayload['robotsMeta']) && is_string($seoPayload['robotsMeta']) ? $seoPayload['robotsMeta'] : null,
                isset($seoPayload['ogTitle']) && is_string($seoPayload['ogTitle']) ? $seoPayload['ogTitle'] : null,
                isset($seoPayload['ogDescription']) && is_string($seoPayload['ogDescription']) ? $seoPayload['ogDescription'] : null,
                isset($seoPayload['ogImage']) && is_string($seoPayload['ogImage']) ? $seoPayload['ogImage'] : null,
                isset($seoPayload['schemaJson']) && is_string($seoPayload['schemaJson']) ? $seoPayload['schemaJson'] : null,
                isset($seoPayload['hreflang']) && is_string($seoPayload['hreflang']) ? $seoPayload['hreflang'] : null,
                isset($seoPayload['domain']) && is_string($seoPayload['domain']) ? $seoPayload['domain'] : null,
                isset($seoPayload['faviconPath']) && is_string($seoPayload['faviconPath']) ? $seoPayload['faviconPath'] : null,
            );

            $seo->save($cfg);
        }

        // Optional: menu assignments (page-scoped by default)
        if (array_key_exists('menuAssignments', $payload) && is_array($payload['menuAssignments'])) {
            $menus = $this->menus ?? new CmsMenuDefinitionService($pdo);
            foreach ($payload['menuAssignments'] as $assignment) {
                if (!is_array($assignment)) {
                    continue;
                }

                $slot = is_string($assignment['slot'] ?? null) ? trim((string) $assignment['slot']) : '';
                $menuId = (int) ($assignment['menuId'] ?? 0);
                $locale = is_string($assignment['locale'] ?? null) ? (string) $assignment['locale'] : null;
                $isActive = (bool) ($assignment['isActive'] ?? true);
                $pageScoped = array_key_exists('pageScoped', $assignment) ? (bool) $assignment['pageScoped'] : true;

                if ($slot === '' || $menuId <= 0) {
                    continue;
                }

                $scopePageId = $pageScoped ? $pageId : null;
                $existingAssignment = $menus->getAssignmentForSlot($ns, $slot, $locale, $scopePageId, false);
                if ($existingAssignment === null) {
                    $menus->createAssignment($ns, $menuId, $scopePageId, $slot, $locale, $isActive);
                } else {
                    $menus->updateAssignment(
                        $ns,
                        $existingAssignment->getId(),
                        $menuId,
                        $scopePageId,
                        $slot,
                        $locale,
                        $isActive
                    );
                }
            }
        }

        return $this->json($response, [
            'status' => 'ok',
            'namespace' => $ns,
            'slug' => $slug,
            'pageId' => $pageId,
        ], 200);
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
