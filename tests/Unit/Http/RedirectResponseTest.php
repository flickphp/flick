<?php

declare(strict_types=1);

use Flick\Http\RedirectResponse;

describe('RedirectResponse', function () {
    test('it stores the URL', function () {
        $response = new RedirectResponse('/thank-you');

        expect($response->getUrl())->toBe('/thank-you');
    });

    test('it defaults to 302 status code', function () {
        $response = new RedirectResponse('/thank-you');

        expect($response->getStatusCode())->toBe(302);
    });

    test('it accepts custom status code', function () {
        $response = new RedirectResponse('/moved', 301);

        expect($response->getStatusCode())->toBe(301);
    });

    test('it converts to array correctly', function () {
        $response = new RedirectResponse('/login', 303);

        $array = $response->toArray();

        expect($array)->toBe([
            'type' => 'redirect',
            'url' => '/login',
            'statusCode' => 303,
        ]);
    });

    test('it handles absolute URLs', function () {
        $response = new RedirectResponse('https://example.com/page');

        expect($response->getUrl())->toBe('https://example.com/page');
    });

    test('it handles URLs with query strings', function () {
        $response = new RedirectResponse('/search?q=test&page=2');

        expect($response->getUrl())->toBe('/search?q=test&page=2');
    });
});
