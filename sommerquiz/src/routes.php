<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (\Slim\App $app) {
    $app->get('/config.js', function (Request $request, Response $response) {
        $path = __DIR__ . '/../public/js/config.js';
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', 'text/javascript');
    });

    $app->post('/config.js', function (Request $request, Response $response) {
        $path = __DIR__ . '/../public/js/config.js';
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string)$request->getBody(), true);
        }
        $content = 'window.quizConfig = ' . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($path, $content);
        return $response->withStatus(204);
    });

    $app->get('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $file = basename($args['file']);
        $path = __DIR__ . '/../public/kataloge/' . $file;
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/kataloge/{file}', function (Request $request, Response $response, array $args) {
        $file = basename($args['file']);
        $path = __DIR__ . '/../public/kataloge/' . $file;
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string)$request->getBody(), true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $response->withStatus(204);
    });
};
