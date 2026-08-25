<?php

declare(strict_types=1);

use Flick\Http\HtmlResponse;
use Flick\Http\JsonResponse;
use Flick\Http\RedirectResponse;
use Flick\Http\ResponseHandlers;

describe('ResponseHandlers', function () {
    describe('redirect handler', function () {
        test('it can be customized', function () {
            $captured = null;
            $handlers = new ResponseHandlers;
            $handlers->onRedirect(function (RedirectResponse $response) use (&$captured) {
                $captured = $response;

                return 'custom-redirect';
            });

            $response = new RedirectResponse('/thank-you');
            $result = $handlers->handleRedirect($response);

            expect($captured)->toBe($response);
            expect($result)->toBe('custom-redirect');
        });

        test('it is fluent', function () {
            $handlers = new ResponseHandlers;
            $result = $handlers->onRedirect(fn ($r) => null);

            expect($result)->toBe($handlers);
        });
    });

    describe('json handler', function () {
        test('it can be customized', function () {
            $captured = null;
            $handlers = new ResponseHandlers;
            $handlers->onJson(function (JsonResponse $response) use (&$captured) {
                $captured = $response;

                return ['intercepted' => true];
            });

            $response = new JsonResponse(['success' => true]);
            $result = $handlers->handleJson($response);

            expect($captured)->toBe($response);
            expect($result)->toBe(['intercepted' => true]);
        });

        test('it is fluent', function () {
            $handlers = new ResponseHandlers;
            $result = $handlers->onJson(fn ($r) => null);

            expect($result)->toBe($handlers);
        });
    });

    describe('exception handler', function () {
        test('it can be customized', function () {
            $captured = null;
            $handlers = new ResponseHandlers;
            $handlers->onException(function (HtmlResponse $response) use (&$captured) {
                $captured = $response;

                return 'error-handled';
            });

            $response = new HtmlResponse('<h1>Error</h1>', 500);
            $result = $handlers->handleException($response);

            expect($captured)->toBe($response);
            expect($result)->toBe('error-handled');
        });

        test('it is fluent', function () {
            $handlers = new ResponseHandlers;
            $result = $handlers->onException(fn ($r) => null);

            expect($result)->toBe($handlers);
        });
    });

    describe('honeypot handler', function () {
        test('it can be customized', function () {
            $called = false;
            $handlers = new ResponseHandlers;
            $handlers->onHoneypot(function () use (&$called) {
                $called = true;

                return 'bot-detected';
            });

            $result = $handlers->handleHoneypot();

            expect($called)->toBeTrue();
            expect($result)->toBe('bot-detected');
        });

        test('it is fluent', function () {
            $handlers = new ResponseHandlers;
            $result = $handlers->onHoneypot(fn () => null);

            expect($result)->toBe($handlers);
        });
    });

    describe('CSRF expired handler', function () {
        test('it can be customized', function () {
            $capturedMessage = null;
            $handlers = new ResponseHandlers;
            $handlers->onCsrfExpired(function (string $message) use (&$capturedMessage) {
                $capturedMessage = $message;

                return 'csrf-handled';
            });

            $result = $handlers->handleCsrfExpired('Session has expired');

            expect($capturedMessage)->toBe('Session has expired');
            expect($result)->toBe('csrf-handled');
        });

        test('it is fluent', function () {
            $handlers = new ResponseHandlers;
            $result = $handlers->onCsrfExpired(fn ($m) => null);

            expect($result)->toBe($handlers);
        });
    });

    describe('chaining', function () {
        test('handlers can be chained fluently', function () {
            $handlers = (new ResponseHandlers)
                ->onRedirect(fn ($r) => 'redirect')
                ->onJson(fn ($r) => 'json')
                ->onException(fn ($r) => 'exception')
                ->onHoneypot(fn () => 'honeypot')
                ->onCsrfExpired(fn ($m) => 'csrf');

            expect($handlers->handleRedirect(new RedirectResponse('/')))->toBe('redirect');
            expect($handlers->handleJson(new JsonResponse([])))->toBe('json');
            expect($handlers->handleException(new HtmlResponse('')))->toBe('exception');
            expect($handlers->handleHoneypot())->toBe('honeypot');
            expect($handlers->handleCsrfExpired('msg'))->toBe('csrf');
        });
    });
});
