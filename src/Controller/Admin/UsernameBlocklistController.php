<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Exception\DuplicateUsernameBlocklistException;
use App\Service\TranslationService;
use App\Service\UsernameBlocklistService;
use DateTimeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;
use function array_map;
use function bin2hex;
use function is_array;
use function json_decode;
use function json_encode;
use function random_bytes;
use function sprintf;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class UsernameBlocklistController
{
    private UsernameBlocklistService $service;

    private ?TranslationService $translator;

    public function __construct(UsernameBlocklistService $service, ?TranslationService $translator = null)
    {
        $this->service = $service;
        $this->translator = $translator;
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $entries = array_map(
            fn (array $entry): array => $this->transformEntry($entry),
            $this->service->getAdminEntries()
        );

        return $view->render($response, 'admin/username_blocklist.twig', [
            'entries' => $entries,
            'csrfToken' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $this->parsePayload($request);
        $term = isset($data['term']) ? (string) $data['term'] : '';

        try {
            $entry = $this->service->add($term);
        } catch (InvalidArgumentException) {
            return $this->jsonError(
                $response,
                $this->translate('error_username_blocklist_invalid'),
                422
            );
        } catch (DuplicateUsernameBlocklistException) {
            return $this->jsonError(
                $response,
                $this->translate('error_username_blocklist_duplicate'),
                409
            );
        } catch (RuntimeException $exception) {
            return $this->jsonError(
                $response,
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : $this->translate('error_username_blocklist_unknown'),
                500
            );
        }

        $message = sprintf(
            $this->translate('message_username_blocklist_created'),
            $entry['term']
        );

        return $this->json(
            $response,
            [
                'status' => 'ok',
                'message' => $message,
                'entry' => $this->transformEntry($entry),
            ],
            201
        );
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $this->jsonError($response, $this->translate('error_username_blocklist_not_found'), 404);
        }

        $entry = $this->service->remove($id);
        if ($entry === null) {
            return $this->jsonError($response, $this->translate('error_username_blocklist_not_found'), 404);
        }

        $message = sprintf(
            $this->translate('message_username_blocklist_deleted'),
            $entry['term']
        );

        return $this->json(
            $response,
            [
                'status' => 'ok',
                'message' => $message,
                'id' => $entry['id'],
                'term' => $entry['term'],
            ]
        );
    }

    /**
     * @param array{id:int,term:string,category:string,created_at:\DateTimeImmutable} $entry
     * @return array{id:int,term:string,created_at:string,created_at_display:string}
     */
    private function transformEntry(array $entry): array
    {
        $createdAt = $entry['created_at'];

        return [
            'id' => $entry['id'],
            'term' => $entry['term'],
            'created_at' => $createdAt->format(DateTimeInterface::ATOM),
            'created_at_display' => $this->formatDate($createdAt),
        ];
    }

    private function formatDate(DateTimeInterface $dateTime): string
    {
        $locale = $this->translator?->getLocale() ?? 'en';
        if ($locale === 'de') {
            return $dateTime->format('d.m.Y H:i');
        }

        return $dateTime->format('Y-m-d H:i');
    }

    private function parsePayload(Request $request): array
    {
        $data = $request->getParsedBody();
        if (is_array($data) && $data !== []) {
            return $data;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function translate(string $key): string
    {
        return $this->translator?->translate($key) ?? $key;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        return $this->json($response, ['error' => $message], $status);
    }
}
