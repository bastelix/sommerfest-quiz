<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Exception\DuplicateUsernameBlocklistException;
use App\Service\ConfigService;
use App\Service\TranslationService;
use App\Service\UsernameBlocklistService;
use InvalidArgumentException;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;
use function array_map;
use function count;
use function array_sum;
use function array_key_exists;
use function bin2hex;
use function dirname;
use function fclose;
use function fgetcsv;
use function fgets;
use function file_get_contents;
use function fopen;
use function fseek;
use function ftell;
use function is_array;
use function is_readable;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function mb_strlen;
use function mb_strtolower;
use function rtrim;
use function random_bytes;
use function sprintf;
use function substr_count;
use function trim;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class UsernameBlocklistController
{
    private UsernameBlocklistService $service;

    private ConfigService $configService;

    private ?TranslationService $translator;

    private string $presetDirectory;

    private const PRESET_CATEGORIES = [
        'nsfw' => 'NSFW',
        'ns_symbols' => '§86a/NS-Bezug',
        'slur' => 'Beleidigung/Slur',
        'general' => 'Allgemein',
        'admin' => UsernameBlocklistService::ADMIN_CATEGORY,
    ];

    public function __construct(
        UsernameBlocklistService $service,
        ConfigService $configService,
        ?TranslationService $translator = null,
        ?string $presetDirectory = null
    ) {
        $this->service = $service;
        $this->configService = $configService;
        $this->translator = $translator;
        $this->presetDirectory = $presetDirectory ?? dirname(__DIR__, 3) . '/resources/blocklists';
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

        $config = $this->configService->getConfig();

        return $view->render($response, 'admin/username_blocklist.twig', [
            'entries' => $entries,
            'csrfToken' => $csrf,
            'role' => $_SESSION['user']['role'] ?? '',
            'domainType' => $request->getAttribute('domainType'),
            'currentPath' => $request->getUri()->getPath(),
            'config' => $config,
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

    public function import(Request $request, Response $response): Response
    {
        $data = $this->parsePayload($request);
        $preset = isset($data['preset']) ? (string) $data['preset'] : '';

        if ($preset === '' || !isset(self::PRESET_CATEGORIES[$preset])) {
            return $this->jsonError($response, $this->translate('error_username_blocklist_import_unknown_preset'), 422);
        }

        try {
            $rows = $this->loadPreset($preset);
        } catch (RuntimeException) {
            return $this->jsonError($response, $this->translate('error_username_blocklist_import_missing'), 404);
        } catch (InvalidArgumentException) {
            return $this->jsonError($response, $this->translate('error_username_blocklist_import_invalid'), 422);
        }

        try {
            $this->service->importEntries($rows);
        } catch (InvalidArgumentException) {
            return $this->jsonError($response, $this->translate('error_username_blocklist_import_invalid'), 422);
        } catch (RuntimeException $exception) {
            return $this->jsonError(
                $response,
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : $this->translate('error_username_blocklist_unknown'),
                500
            );
        }

        $summary = $this->summarizeRows($rows);
        $total = array_sum($summary);
        $presetLabel = $this->translate('label_username_blocklist_preset_' . $preset);
        $message = sprintf(
            $this->translate('message_username_blocklist_imported'),
            $total,
            $presetLabel
        );

        $entries = array_map(
            fn (array $entry): array => $this->transformEntry($entry),
            $this->service->getAdminEntries()
        );

        return $this->json(
            $response,
            [
                'status' => 'ok',
                'message' => $message,
                'entries' => $entries,
                'summary' => $summary,
            ]
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

    /**
     * @return list<array{term:string,category:string}>
     */
    private function loadPreset(string $preset): array
    {
        $category = self::PRESET_CATEGORIES[$preset];
        $basePath = rtrim($this->presetDirectory, '/\\');
        $csvPath = $basePath . '/' . $preset . '.csv';
        $jsonPath = $basePath . '/' . $preset . '.json';

        if (is_readable($csvPath)) {
            return $this->loadCsvPreset($csvPath, $category);
        }

        if (is_readable($jsonPath)) {
            return $this->loadJsonPreset($jsonPath, $category);
        }

        throw new RuntimeException('Preset file not found.');
    }

    /**
     * @return list<array{term:string,category:string}>
     */
    private function loadCsvPreset(string $path, string $category): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open preset file.');
        }

        try {
            $delimiter = $this->detectDelimiter($handle);
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false) {
                return [];
            }

            $termIndex = null;
            foreach ($header as $index => $column) {
                if (mb_strtolower(trim((string) $column)) === 'term') {
                    $termIndex = $index;
                    break;
                }
            }

            if ($termIndex === null) {
                throw new InvalidArgumentException('CSV file must contain a "term" column.');
            }

            $rows = [];
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {

                $term = isset($values[$termIndex]) ? trim((string) $values[$termIndex]) : '';
                if ($term === '') {
                    continue;
                }

                $rows[] = [
                    'term' => $term,
                    'category' => $category,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array{term:string,category:string}>
     */
    private function loadJsonPreset(string $path, string $category): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read preset file.');
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('JSON preset must contain an array of entries.');
        }

        $rows = [];
        foreach ($decoded as $index => $row) {
            if (is_array($row) && array_key_exists('term', $row)) {
                $term = (string) $row['term'];
            } elseif (is_string($row)) {
                $term = $row;
            } else {
                throw new InvalidArgumentException(sprintf('Entry %d in preset is invalid.', $index));
            }

            $term = trim($term);
            if ($term === '') {
                continue;
            }

            $rows[] = [
                'term' => $term,
                'category' => $category,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{term:string,category:string}> $rows
     * @return array<string,int>
     */
    private function summarizeRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $summary = [];
        foreach ($rows as $row) {
            $term = mb_strtolower(trim($row['term']));
            if ($term === '' || mb_strlen($term) < 3) {
                continue;
            }

            $category = $row['category'];
            if ($category === '') {
                continue;
            }

            $summary[$category][$term] = true;
        }

        $result = [];
        foreach ($summary as $category => $terms) {
            $result[$category] = count($terms);
        }

        ksort($result);

        return $result;
    }

    /**
     * @param resource $handle
     */
    private function detectDelimiter($handle): string
    {
        $position = ftell($handle);
        $line = fgets($handle);
        $delimiter = ',';

        if ($line !== false) {
            $semicolonCount = substr_count($line, ';');
            $commaCount = substr_count($line, ',');
            if ($semicolonCount > 0 && $semicolonCount >= $commaCount) {
                $delimiter = ';';
            }
        }

        if ($position !== false) {
            fseek($handle, $position);
        }

        return $delimiter;
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
