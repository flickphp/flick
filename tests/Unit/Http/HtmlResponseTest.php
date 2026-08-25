<?php

declare(strict_types=1);

use Flick\Http\HtmlResponse;

describe('HtmlResponse', function () {
    test('it stores the content', function () {
        $content = '<h1>Error</h1><p>Something went wrong</p>';
        $response = new HtmlResponse($content);

        expect($response->getContent())->toBe($content);
    });

    test('it defaults to 200 status code', function () {
        $response = new HtmlResponse('<p>Hello</p>');

        expect($response->getStatusCode())->toBe(200);
    });

    test('it accepts custom status code', function () {
        $response = new HtmlResponse('<h1>Not Found</h1>', 404);

        expect($response->getStatusCode())->toBe(404);
    });

    test('it converts to array correctly', function () {
        $content = '<html><body>Error</body></html>';
        $response = new HtmlResponse($content, 500);

        $array = $response->toArray();

        expect($array)->toBe([
            'type' => 'html',
            'content' => $content,
            'statusCode' => 500,
        ]);
    });

    test('it handles empty content', function () {
        $response = new HtmlResponse('');

        expect($response->getContent())->toBe('');
    });

    test('it handles multiline HTML content', function () {
        $content = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Error</title></head>
<body>
    <h1>Whoops</h1>
    <p>An error occurred</p>
</body>
</html>
HTML;
        $response = new HtmlResponse($content, 500);

        expect($response->getContent())->toBe($content);
        expect($response->getStatusCode())->toBe(500);
    });
});
