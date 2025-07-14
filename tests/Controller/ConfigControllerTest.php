<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\ConfigController;
use App\Service\ConfigService;
use Tests\TestCase;
use Slim\Psr7\Response;

class ConfigControllerTest extends TestCase
{
    public function testGetNotFound(): void
    {
        $path = dirname(__DIR__, 2) . '/data/config.json';
        $backup = $path . '.bak';
        rename($path, $backup);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
                adminUser TEXT,
                adminPass TEXT,
                QRRestrict INTEGER,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $controller = new ConfigController(new ConfigService($pdo));
        $request = $this->createRequest('GET', '/config.json');
        $response = $controller->get($request, new Response());

        $this->assertEquals(404, $response->getStatusCode());
        rename($backup, $path);
    }

    public function testPostAndGet(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
                adminUser TEXT,
                adminPass TEXT,
                QRRestrict INTEGER,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $service = new ConfigService($pdo);
        $controller = new ConfigController($service);
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'event-manager'];

        $request = $this->createRequest('POST', '/config.json');
        $request = $request->withParsedBody(['foo' => 'bar']);
        $postResponse = $controller->post($request, new Response());
        $this->assertEquals(204, $postResponse->getStatusCode());

        $getResponse = $controller->get($this->createRequest('GET', '/config.json'), new Response());
        $this->assertEquals(200, $getResponse->getStatusCode());
        $this->assertStringContainsString('foo', (string) $getResponse->getBody());
        session_destroy();
    }

    public function testPostInvalidJson(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
                adminUser TEXT,
                adminPass TEXT,
                QRRestrict INTEGER,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $service = new ConfigService($pdo);
        $controller = new ConfigController($service);

        $request = $this->createRequest('POST', '/config.json', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '{invalid');
        rewind($stream);
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($stream);
        $request = $request->withBody($stream);

        $response = $controller->post($request, new Response());
        $this->assertEquals(400, $response->getStatusCode());
        session_destroy();
    }

    public function testPostDeniedForNonAdmin(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 2, 'role' => 'user'];
        $request = $this->createRequest('POST', '/config.json');
        $request = $request->withParsedBody(['foo' => 'bar']);
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        session_destroy();
    }
}
