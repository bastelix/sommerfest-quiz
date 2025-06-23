<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class ResultControllerTest extends TestCase
{
    public function testQuestionResultsEndpoint(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/question-results.json');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
