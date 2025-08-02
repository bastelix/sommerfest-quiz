<?php

declare(strict_types=1);

namespace App\Controller {
    function rmdir(string $dir): bool
    {
        return \Tests\Controller\BackupControllerTest::callRmdir($dir);
    }
}

namespace Tests\Controller {

    use App\Controller\BackupController;
    use Slim\Psr7\Response;
    use Tests\TestCase;

    class BackupControllerTest extends TestCase
    {
        /** @var callable|null */
        public static $rmdirCallback = null;

        public static function callRmdir(string $dir): bool
        {
            if (self::$rmdirCallback !== null) {
                return (self::$rmdirCallback)($dir);
            }
            return \rmdir($dir);
        }

        protected function tearDown(): void
        {
            self::$rmdirCallback = null;
            parent::tearDown();
        }

        public function testDeleteSuccess(): void
        {
            $base = sys_get_temp_dir() . '/bct_' . uniqid();
            mkdir($base . '/ok', 0777, true);
            file_put_contents($base . '/ok/test.txt', 'a');
            $this->assertDirectoryExists($base . '/ok');

            $controller = new BackupController($base);
            $res = $controller->delete(
                $this->createRequest('DELETE', '/backups/ok'),
                new Response(),
                ['name' => 'ok']
            );

            $this->assertEquals(204, $res->getStatusCode());
            $this->assertDirectoryDoesNotExist($base . '/ok');
            \rmdir($base);
        }

        public function testDeleteFailure(): void
        {
            $base = sys_get_temp_dir() . '/bct_' . uniqid();
            mkdir($base . '/fail', 0777, true);
            file_put_contents($base . '/fail/test.txt', 'a');
            $this->assertDirectoryExists($base . '/fail');

            self::$rmdirCallback = fn(string $dir) => false;

            $controller = new BackupController($base);
            $res = $controller->delete(
                $this->createRequest('DELETE', '/backups/fail'),
                new Response(),
                ['name' => 'fail']
            );

            $this->assertEquals(500, $res->getStatusCode());
            $body = (string) $res->getBody();
            $this->assertStringContainsString('delete backup directory', $body);

            // cleanup
            self::$rmdirCallback = null;
            unlink($base . '/fail/test.txt');
            \rmdir($base . '/fail');
            \rmdir($base);
            $this->assertDirectoryDoesNotExist($base);
        }

        public function testDeleteInvalidName(): void
        {
            $base = sys_get_temp_dir() . '/bct_' . uniqid();
            mkdir($base, 0777, true);
            $this->assertDirectoryExists($base);

            $controller = new BackupController($base);
            $res = $controller->delete(
                $this->createRequest('DELETE', '/backups/..'),
                new Response(),
                ['name' => '..']
            );

            $this->assertEquals(400, $res->getStatusCode());

            \rmdir($base);
            $this->assertDirectoryDoesNotExist($base);
        }
    }
}
