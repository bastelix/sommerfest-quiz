<?php

declare(strict_types=1);

namespace Tests\Service\RagChat;

use App\Service\RagChat\HttpChatResponder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class HttpChatResponderTest extends TestCase
{
    /**
     * @dataProvider responseFieldProvider
     */
    public function testResponderAcceptsCustomResponseFields(string $field): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([$field => 'Hallo!'])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $responder = new HttpChatResponder('https://chat.example.com/v1/chat', $client);

        $messages = [
            ['role' => 'user', 'content' => 'Sag Hallo.'],
        ];
        $context = [
            ['id' => 'chunk-1', 'text' => 'stub context', 'score' => 0.7, 'metadata' => []],
        ];

        $answer = $responder->respond($messages, $context);

        self::assertSame('Hallo!', $answer);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function responseFieldProvider(): array
    {
        return [
            'response' => ['response'],
            'result' => ['result'],
        ];
    }
}
