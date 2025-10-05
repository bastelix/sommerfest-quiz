<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\RagChat\DomainDocumentStorage;
use App\Service\RagChat\DomainIndexManager;
use App\Support\DomainNameHelper;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class DomainChatKnowledgeController
{
    private DomainDocumentStorage $storage;

    private DomainIndexManager $indexManager;

    public function __construct(DomainDocumentStorage $storage, DomainIndexManager $indexManager)
    {
        $this->storage = $storage;
        $this->indexManager = $indexManager;
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $documents = $this->storage->listDocuments($domain);

        return $this->json($response, [
            'domain' => $domain,
            'documents' => $documents,
        ]);
    }

    public function upload(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $files = $request->getUploadedFiles();
        $file = $files['document'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->json($response, ['error' => 'missing-file'], 400);
        }

        try {
            $document = $this->storage->storeDocument($domain, $file);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 500);
        }

        return $this->json($response, ['document' => $document], 201);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $id = isset($args['id']) ? (string) $args['id'] : '';
        if ($id === '') {
            return $this->json($response, ['error' => 'invalid-document'], 400);
        }

        try {
            $this->storage->deleteDocument($domain, $id);
        } catch (RuntimeException | InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 404);
        }

        return $this->json($response, ['success' => true]);
    }

    public function download(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        $indexPath = $this->storage->getIndexPath($domain);
        if (!is_file($indexPath)) {
            return $this->json($response, ['error' => 'index-not-found'], 404);
        }

        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            return $this->json($response, ['error' => 'Failed to read index file.'], 500);
        }

        $safeDomain = preg_replace('/[^a-z0-9._-]+/i', '-', $domain) ?? $domain;
        $filename = sprintf('domain-index-%s.json', $safeDomain);

        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
    }

    public function rebuild(Request $request, Response $response): Response
    {
        try {
            $domain = $this->extractDomain($request);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        }

        try {
            $result = $this->indexManager->rebuild($domain);
        } catch (RuntimeException $exception) {
            $message = trim($exception->getMessage());
            if ($message === '') {
                $message = 'Domain index rebuild failed.';
            }

            return $this->json($response, ['error' => $message], 422);
        }

        return $this->json($response, $result);
    }

    private function extractDomain(Request $request): string
    {
        $domain = '';
        $params = $request->getQueryParams();
        if (isset($params['domain'])) {
            $domain = (string) $params['domain'];
        }

        if ($domain === '') {
            $body = $request->getParsedBody();
            if (is_array($body) && isset($body['domain'])) {
                $domain = (string) $body['domain'];
            } elseif ($request->getHeaderLine('Content-Type') === 'application/json') {
                $raw = (string) $request->getBody();
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && isset($decoded['domain'])) {
                        $domain = (string) $decoded['domain'];
                    }
                }
            }
        }

        $normalized = DomainNameHelper::canonicalizeSlug($domain);
        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid domain parameter.');
        }

        return $normalized;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        try {
            $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode response.', 0, $exception);
        }

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}

