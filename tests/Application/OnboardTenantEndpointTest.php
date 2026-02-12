<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use PDO;
use Tests\TestCase;

class OnboardTenantEndpointTest extends TestCase
{
    public function testEntrypointExportsWildcardHostForSingleContainer(): void
    {
        $envContent = <<<ENV
TENANT_SINGLE_CONTAINER=1
ENABLE_WILDCARD_SSL=1
MAIN_DOMAIN=quiz.example.test
VIRTUAL_HOST=app.example.test, marketing.example.test
LETSENCRYPT_HOST=quiz.example.test,marketing.example.test

ENV;

        $exported = $this->runEntrypointWithEnv($envContent);

        $wildcardHost = '~^([a-z0-9-]+\.)?quiz.example.test$';

        $this->assertArrayHasKey('VIRTUAL_HOST', $exported);
        $this->assertArrayHasKey('LETSENCRYPT_HOST', $exported);

        $this->assertSame(
            'app.example.test,marketing.example.test,' . $wildcardHost,
            $exported['VIRTUAL_HOST']
        );
        $this->assertSame(
            'quiz.example.test,marketing.example.test,*.quiz.example.test',
            $exported['LETSENCRYPT_HOST']
        );
    }

    public function testEntrypointLeavesHostsUntouchedWhenFlagDisabled(): void
    {
        $envContent = <<<ENV
TENANT_SINGLE_CONTAINER=0
MAIN_DOMAIN=quiz.example.test
VIRTUAL_HOST=app.example.test,marketing.example.test
LETSENCRYPT_HOST=app.example.test

ENV;

        $exported = $this->runEntrypointWithEnv($envContent);

        $this->assertArrayHasKey('VIRTUAL_HOST', $exported);
        $this->assertArrayHasKey('LETSENCRYPT_HOST', $exported);

        $this->assertSame('app.example.test,marketing.example.test', $exported['VIRTUAL_HOST']);
        $this->assertSame('app.example.test', $exported['LETSENCRYPT_HOST']);
    }

    public function testEntrypointStripsRegexHostsFromLetsEncryptList(): void
    {
        $regexHost = '~^([a-z0-9-]+\.)?quiz.example.test$';

        $envContent = <<<ENV
VIRTUAL_HOST=app.example.test
LETSENCRYPT_HOST=app.example.test,{$regexHost},quiz.example.test

ENV;

        $exported = $this->runEntrypointWithEnv($envContent);

        $this->assertArrayHasKey('LETSENCRYPT_HOST', $exported);
        $this->assertSame('app.example.test,quiz.example.test', $exported['LETSENCRYPT_HOST']);
    }

    public function testEntrypointLeavesHostsUntouchedWithoutBaseDomain(): void
    {
        $envContent = <<<ENV
TENANT_SINGLE_CONTAINER=1
VIRTUAL_HOST=app.example.test
LETSENCRYPT_HOST=app.example.test

ENV;

        $exported = $this->runEntrypointWithEnv($envContent);

        $this->assertArrayHasKey('VIRTUAL_HOST', $exported);
        $this->assertArrayHasKey('LETSENCRYPT_HOST', $exported);

        $this->assertSame('app.example.test', $exported['VIRTUAL_HOST']);
        $this->assertSame('app.example.test', $exported['LETSENCRYPT_HOST']);
    }

    public function testSingleContainerProvisionsMissingCertificates(): void
    {
        putenv('TENANT_SINGLE_CONTAINER=1');
        $_ENV['TENANT_SINGLE_CONTAINER'] = '1';
        putenv('MAIN_DOMAIN=quiz.example.test');
        $_ENV['MAIN_DOMAIN'] = 'quiz.example.test';

        $projectRoot = dirname(__DIR__, 2);
        $logPath = $projectRoot . '/logs/onboarding.log';
        $originalLog = is_file($logPath) ? file_get_contents($logPath) : null;

        $certDir = $projectRoot . '/certs';
        $certPath = $certDir . '/quiz.example.test.crt';
        $keyPath = $certDir . '/quiz.example.test.key';

        $certDirPreviouslyExisted = is_dir($certDir);
        if (!$certDirPreviouslyExisted) {
            mkdir($certDir, 0775, true);
        }

        $originalCert = is_file($certPath) ? file_get_contents($certPath) : null;
        $originalKey = is_file($keyPath) ? file_get_contents($keyPath) : null;

        if (is_file($certPath)) {
            unlink($certPath);
        }
        if (is_file($keyPath)) {
            unlink($keyPath);
        }

        $scriptPath = tempnam(sys_get_temp_dir(), 'provision-wildcard-');
        $scriptTemplate = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

domain=""
while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --domain)
      shift
      domain="${1:-}"
      ;;
    *)
      shift || true
      ;;
  esac
done

if [[ -z "${domain:-}" ]]; then
  echo "missing domain" >&2
  exit 1
fi

cert_dir=__CERT_DIR__
mkdir -p "$cert_dir"
echo "dummy-cert" > "$cert_dir/${domain}.crt"
echo "dummy-key" > "$cert_dir/${domain}.key"
BASH;

        $scriptContent = str_replace('__CERT_DIR__', escapeshellarg($certDir), $scriptTemplate) . PHP_EOL;
        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);

        putenv('PROVISION_WILDCARD_SCRIPT=' . $scriptPath);
        $_ENV['PROVISION_WILDCARD_SCRIPT'] = $scriptPath;

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $migrationsDir = dirname(__DIR__, 2) . '/migrations';
        Migrator::setHook(null);
        Migrator::migrate($pdo, $migrationsDir);

        Migrator::setHook(static function (): bool {
            return false;
        });

        Database::setFactory(static function () use ($pdo): PDO {
            return $pdo;
        });
        $this->setDatabase($pdo);

        putenv('DISPLAY_ERROR_DETAILS=1');
        $_ENV['DISPLAY_ERROR_DETAILS'] = '1';
        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';
        putenv('PASSWORD_RESET_SECRET=test-secret');
        $_ENV['PASSWORD_RESET_SECRET'] = 'test-secret';

        try {
            $pdo->exec("INSERT INTO tenants(uid, subdomain) VALUES('t-single-missing', 'singleslug')");
            $pdo->exec("INSERT INTO domains(host, normalized_host, zone, namespace, is_active) VALUES('quiz.example.test', 'quiz.example.test', 'quiz.example.test', 'public', 1)");
            $pdo->exec("INSERT OR IGNORE INTO namespaces(namespace, is_active) VALUES('public', 1)");

            $app = $this->getAppInstance();

            putenv('RUN_MIGRATIONS_ON_REQUEST=1');
            $_ENV['RUN_MIGRATIONS_ON_REQUEST'] = '1';

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION['user'] = ['id' => 1, 'role' => Roles::ADMIN];
            $_SESSION['csrf_token'] = 'csrf-token';

            $request = $this->createRequest('POST', '/api/tenants/singleslug/onboard', [
                'HTTP_ACCEPT' => 'application/json',
                'X-Requested-With' => 'fetch',
                'X-CSRF-Token' => 'csrf-token',
            ]);

            $response = $app->handle($request);

            $this->assertSame(200, $response->getStatusCode());

            $payload = json_decode((string) $response->getBody(), true);
            $this->assertIsArray($payload);
            $this->assertSame('completed', $payload['status'] ?? null);
            $this->assertSame('singleslug', $payload['tenant'] ?? null);
            $this->assertSame('single-container', $payload['mode'] ?? null);
            $this->assertFileExists($certPath);
            $this->assertFileExists($keyPath);
        } finally {
            if ($originalLog === null) {
                if (is_file($logPath)) {
                    unlink($logPath);
                }
            } else {
                file_put_contents($logPath, $originalLog);
            }

            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }

            if ($originalCert === null) {
                if (is_file($certPath)) {
                    unlink($certPath);
                }
            } else {
                file_put_contents($certPath, $originalCert);
            }

            if ($originalKey === null) {
                if (is_file($keyPath)) {
                    unlink($keyPath);
                }
            } else {
                file_put_contents($keyPath, $originalKey);
            }

            if (!$certDirPreviouslyExisted && is_dir($certDir)) {
                $entries = array_diff(scandir($certDir), ['.', '..']);
                if (count($entries) === 0) {
                    rmdir($certDir);
                }
            }

            putenv('TENANT_SINGLE_CONTAINER');
            unset($_ENV['TENANT_SINGLE_CONTAINER']);
            putenv('MAIN_DOMAIN');
            unset($_ENV['MAIN_DOMAIN']);
            putenv('RUN_MIGRATIONS_ON_REQUEST');
            unset($_ENV['RUN_MIGRATIONS_ON_REQUEST']);
            putenv('DISPLAY_ERROR_DETAILS');
            unset($_ENV['DISPLAY_ERROR_DETAILS']);
            putenv('DASHBOARD_TOKEN_SECRET');
            unset($_ENV['DASHBOARD_TOKEN_SECRET']);
            putenv('PROVISION_WILDCARD_SCRIPT');
            unset($_ENV['PROVISION_WILDCARD_SCRIPT']);
            putenv('PASSWORD_RESET_SECRET');
            unset($_ENV['PASSWORD_RESET_SECRET']);
            Database::setFactory(null);
            Migrator::setHook(null);
        }
    }

    public function testSingleContainerSkipsDockerProvisioning(): void
    {
        putenv('TENANT_SINGLE_CONTAINER=1');
        $_ENV['TENANT_SINGLE_CONTAINER'] = '1';
        putenv('MAIN_DOMAIN=quiz.example.test');
        $_ENV['MAIN_DOMAIN'] = 'quiz.example.test';

        $projectRoot = dirname(__DIR__, 2);
        $logPath = $projectRoot . '/logs/onboarding.log';
        $originalLog = is_file($logPath) ? file_get_contents($logPath) : null;

        $certDir = $projectRoot . '/certs';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0775, true);
        }
        $certPath = $certDir . '/quiz.example.test.crt';
        $keyPath = $certDir . '/quiz.example.test.key';
        file_put_contents($certPath, 'dummy-cert');
        file_put_contents($keyPath, 'dummy-key');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $migrationsDir = dirname(__DIR__, 2) . '/migrations';
        Migrator::setHook(null);
        Migrator::migrate($pdo, $migrationsDir);

        Migrator::setHook(static function (): bool {
            return false;
        });

        Database::setFactory(static function () use ($pdo): PDO {
            return $pdo;
        });
        $this->setDatabase($pdo);

        putenv('DISPLAY_ERROR_DETAILS=1');
        $_ENV['DISPLAY_ERROR_DETAILS'] = '1';
        putenv('DASHBOARD_TOKEN_SECRET=test-secret');
        $_ENV['DASHBOARD_TOKEN_SECRET'] = 'test-secret';
        putenv('PASSWORD_RESET_SECRET=test-secret');
        $_ENV['PASSWORD_RESET_SECRET'] = 'test-secret';

        try {
            $pdo->exec("INSERT INTO tenants(uid, subdomain) VALUES('t-single', 'singleslug')");
            $pdo->exec("INSERT INTO domains(host, normalized_host, zone, namespace, is_active) VALUES('quiz.example.test', 'quiz.example.test', 'quiz.example.test', 'public', 1)");
            $pdo->exec("INSERT OR IGNORE INTO namespaces(namespace, is_active) VALUES('public', 1)");

            $app = $this->getAppInstance();

            putenv('RUN_MIGRATIONS_ON_REQUEST=1');
            $_ENV['RUN_MIGRATIONS_ON_REQUEST'] = '1';

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION['user'] = ['id' => 1, 'role' => Roles::ADMIN];
            $_SESSION['csrf_token'] = 'csrf-token';

            $request = $this->createRequest('POST', '/api/tenants/singleslug/onboard', [
                'HTTP_ACCEPT' => 'application/json',
                'X-Requested-With' => 'fetch',
                'X-CSRF-Token' => 'csrf-token',
            ]);

            $response = $app->handle($request);

            $this->assertSame(200, $response->getStatusCode());

            $payload = json_decode((string) $response->getBody(), true);
            $this->assertIsArray($payload);
            $this->assertSame('completed', $payload['status'] ?? null);
            $this->assertSame('singleslug', $payload['tenant'] ?? null);
            $this->assertSame('single-container', $payload['mode'] ?? null);
            $state = $pdo->query("SELECT onboarding_state FROM tenants WHERE subdomain='singleslug'")
                ->fetchColumn();
            $this->assertSame('provisioned', $state);
        } finally {
            if ($originalLog === null) {
                if (is_file($logPath)) {
                    unlink($logPath);
                }
            } else {
                file_put_contents($logPath, $originalLog);
            }

            if (is_file($certPath)) {
                unlink($certPath);
            }
            if (is_file($keyPath)) {
                unlink($keyPath);
            }
            if (is_dir($certDir) && count(scandir($certDir)) === 2) {
                rmdir($certDir);
            }

            putenv('TENANT_SINGLE_CONTAINER');
            unset($_ENV['TENANT_SINGLE_CONTAINER']);
            putenv('MAIN_DOMAIN');
            unset($_ENV['MAIN_DOMAIN']);
            putenv('RUN_MIGRATIONS_ON_REQUEST');
            unset($_ENV['RUN_MIGRATIONS_ON_REQUEST']);
            putenv('DISPLAY_ERROR_DETAILS');
            unset($_ENV['DISPLAY_ERROR_DETAILS']);
            putenv('DASHBOARD_TOKEN_SECRET');
            unset($_ENV['DASHBOARD_TOKEN_SECRET']);
            putenv('PASSWORD_RESET_SECRET');
            unset($_ENV['PASSWORD_RESET_SECRET']);
            Database::setFactory(null);
            Migrator::setHook(null);
        }
    }

    /**
     * @return array<string, string>
     */
    private function runEntrypointWithEnv(string $envContent): array
    {
        $projectRoot = dirname(__DIR__, 2);
        $envFile = $projectRoot . '/.env';
        $originalEnv = is_file($envFile) ? file_get_contents($envFile) : null;

        $varsToReset = ['TENANT_SINGLE_CONTAINER', 'MAIN_DOMAIN', 'DOMAIN', 'VIRTUAL_HOST', 'LETSENCRYPT_HOST', 'ENABLE_WILDCARD_SSL'];
        $previousEnv = [];

        foreach ($varsToReset as $var) {
            $value = getenv($var);
            $previousEnv[$var] = $value === false ? null : $value;
            putenv($var);
            unset($_ENV[$var]);
        }

        try {
            file_put_contents($envFile, $envContent);

            $command = sprintf('cd %s && ./docker-entrypoint.sh env', escapeshellarg($projectRoot));
            $output = shell_exec($command);
            $this->assertIsString($output, 'Entrypoint script did not run successfully');

            $exported = [];
            foreach (preg_split('/\r?\n/', trim((string) $output)) as $line) {
                if ($line === '' || strpos($line, '=') === false) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $exported[$name] = $value;
            }

            return $exported;
        } finally {
            if ($originalEnv === null) {
                if (is_file($envFile)) {
                    unlink($envFile);
                }
            } else {
                file_put_contents($envFile, $originalEnv);
            }

            foreach ($previousEnv as $var => $value) {
                if ($value === null) {
                    putenv($var);
                    unset($_ENV[$var]);
                } else {
                    putenv(sprintf('%s=%s', $var, $value));
                    $_ENV[$var] = $value;
                }
            }
        }
    }
}
