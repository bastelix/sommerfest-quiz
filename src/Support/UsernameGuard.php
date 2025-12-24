<?php
declare(strict_types=1);

namespace App\Support;

use App\Support\Censor\BanBuilderCensor;
use App\Support\Censor\SimpleCensor;
use App\Support\Censor\UsernameCensor;
use PDO;
use PDOException;
use Throwable;
use function array_filter;
use function array_fill;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function implode;
use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function mb_strtolower;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Validates usernames against a configurable blocklist.
 */
final class UsernameGuard
{
    public const DATABASE_CATEGORIES = [
        'NSFW',
        '§86a/NS-Bezug',
        'Beleidigung/Slur',
        'Allgemein',
        'Admin',
    ];

    /**
     * @var list<string>
     */
    private array $blockedUsernames;

    /**
     * @var list<string>
     */
    private array $blockedPatterns;

    private UsernameCensor $censor;

    private ?PDO $pdo;

    private bool $databaseLoaded = false;

    /**
     * @param array{usernames?:mixed,patterns?:mixed} $config
     */
    public function __construct(array $config, ?UsernameCensor $censor = null, ?PDO $pdo = null)
    {
        $usernames = $config['usernames'] ?? [];
        $patterns = $config['patterns'] ?? [];

        $usernameList = is_array($usernames) ? $usernames : [];
        $this->blockedUsernames = array_values(array_filter(array_map(
            static function ($value): ?string {
                if (!is_string($value)) {
                    return null;
                }

                $value = trim($value);
                if ($value === '') {
                    return null;
                }

                return mb_strtolower($value);
            },
            $usernameList
        )));

        $patternList = is_array($patterns) ? $patterns : [];
        $this->blockedPatterns = array_values(array_filter(array_map(
            static function ($value): ?string {
                if (!is_string($value)) {
                    return null;
                }

                $value = trim($value);
                return $value === '' ? null : $value;
            },
            $patternList
        )));

        $this->pdo = $pdo;

        if ($censor === null) {
            if (BanBuilderCensor::isSupported()) {
                try {
                    $censor = BanBuilderCensor::create();
                } catch (Throwable) {
                    $censor = new SimpleCensor();
                }
            } else {
                $censor = new SimpleCensor();
            }
        }

        $this->censor = $censor;

        if ($this->blockedUsernames !== []) {
            $this->censor->addFromArray($this->blockedUsernames);
        }
    }

    public static function fromConfigFile(?string $path = null, ?PDO $pdo = null): self
    {
        $path = $path ?? dirname(__DIR__, 2) . '/config/blocked_usernames.php';
        $config = [];
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        return new self($config, null, $pdo);
    }

    public function assertAllowed(string $username): void
    {
        $normalized = mb_strtolower(trim($username));
        if ($normalized === '') {
            return;
        }

        $this->loadDatabaseEntries();

        foreach ($this->blockedUsernames as $blocked) {
            if ($normalized === $blocked) {
                throw UsernameBlockedException::forExactMatch($username);
            }
        }

        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                throw UsernameBlockedException::forPatternMatch($username);
            }
        }

        $result = $this->censor->censorString($normalized);
        if ($result['matched'] !== []) {
            throw UsernameBlockedException::forPatternMatch($username);
        }
    }

    private function loadDatabaseEntries(): void
    {
        if ($this->databaseLoaded || $this->pdo === null) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count(self::DATABASE_CATEGORIES), '?'));
        $sql = sprintf('SELECT term FROM username_blocklist WHERE category IN (%s)', $placeholders);

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            $this->databaseLoaded = true;
            return;
        }

        try {
            $stmt->execute(self::DATABASE_CATEGORIES);
        } catch (PDOException) {
            $this->databaseLoaded = true;
            return;
        }

        $terms = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $term = mb_strtolower(trim((string) ($row['term'] ?? '')));
            if ($term === '') {
                continue;
            }
            $terms[] = $term;
        }

        if ($terms !== []) {
            $this->blockedUsernames = array_values(array_unique(array_merge($this->blockedUsernames, $terms)));
            $this->censor->addFromArray($terms);
        }

        $this->databaseLoaded = true;
    }
}
