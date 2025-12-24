<?php

declare(strict_types=1);

namespace Tests\Controller;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

use App\Controller\Marketing\MarketingChatController;
use Tests\TestCase;
use App\Service\RagChat\RagChatResponse;
use App\Service\RagChat\RagChatServiceInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function json_decode;
use function json_encode;

final class MarketingChatControllerTest extends TestCase
{
    public function testChatPrefersSlugOverHost(): void
    {
        $service = new FakeRagChatService();
        $controller = new MarketingChatController('calserver', $service);

        $request = $this->createRequest(
            'POST',
            '/calserver/chat',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode(['question' => 'Legacy?'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();
        $request = $request
            ->withUri($request->getUri()->withHost('legacy.example.com'));

        $responseFactory = new ResponseFactory();
        $response = $controller($request, $responseFactory->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Legacy?', $payload['question']);
        $this->assertSame('Answer for calserver', $payload['answer']);
        $this->assertSame([], $payload['context']);
        $this->assertSame('calserver', $service->lastDomain);
        $this->assertSame('de', $service->lastLocale);
    }

    public function testChatFallsBackToHostWhenSlugMissing(): void
    {
        $service = new FakeRagChatService();
        $controller = new MarketingChatController(null, $service);

        $request = $this->createRequest(
            'POST',
            '/calserver/chat',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode(['question' => 'Legacy?'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();
        $request = $request->withUri($request->getUri()->withHost('legacy.example.com'));

        $responseFactory = new ResponseFactory();
        $result = $controller($request, $responseFactory->createResponse());

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Legacy?', $payload['question']);
        $this->assertSame('Answer for legacy.example.com', $payload['answer']);
        $this->assertSame([], $payload['context']);
        $this->assertSame('legacy.example.com', $service->lastDomain);
        $this->assertSame('de', $service->lastLocale);
    }

    public function testHoneypotReturnsNoContent(): void
    {
        $service = new FakeRagChatService();
        $controller = new MarketingChatController('calserver', $service);

        $request = $this->createRequest(
            'POST',
            '/calserver/chat',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        );
        $request->getBody()->write(json_encode(['question' => 'Legacy?', 'company' => 'Acme Inc.'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $responseFactory = new ResponseFactory();
        $result = $controller($request, $responseFactory->createResponse());

        $this->assertSame(204, $result->getStatusCode());
        $this->assertSame('', (string) $result->getBody());
        $this->assertNull($service->lastQuestion);
    }
}

final class FakeRagChatService implements RagChatServiceInterface
{
    public ?string $lastQuestion = null;

    public ?string $lastLocale = null;

    public ?string $lastDomain = null;

    public function answer(string $question, string $locale = 'de', ?string $domain = null): RagChatResponse
    {
        $this->lastQuestion = $question;
        $this->lastLocale = $locale;
        $this->lastDomain = $domain;

        return new RagChatResponse($question, 'Answer for ' . ($domain ?? 'global'), []);
    }
}
