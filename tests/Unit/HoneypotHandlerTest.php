<?php

declare(strict_types=1);

use Flick\Flick;

describe('Honeypot Handler Integration', function () {
    beforeEach(function () {
        // Save the current exception handler
        $this->previousHandler = snapshotExceptionHandler();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];

        // Restore the original exception handler
        unwindExceptionHandlersTo($this->previousHandler);
    });

    test('custom honeypot handler is called on POST with filled honeypot', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['website'] = 'spam-value'; // Honeypot field filled

        $form = new Flick([
            'honeypot' => 'website',
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null; // Return instead of exit
            },
        ]);

        expect($handlerCalled)->toBeTrue();
    });

    test('custom honeypot handler is called on GET with filled honeypot', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['website'] = 'spam-value';

        $form = new Flick([
            'honeypot' => 'website',
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeTrue();
    });

    test('honeypot handler is not called when honeypot field is empty', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['website'] = ''; // Honeypot field empty (legitimate user)
        $_POST['email'] = 'user@example.com';

        $form = new Flick([
            'honeypot' => 'website',
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeFalse();
    });

    test('honeypot handler is not called when honeypot field is not set', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'user@example.com';

        $form = new Flick([
            'honeypot' => 'website',
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeFalse();
    });

    test('honeypot handler is not called when honeypot not configured', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['website'] = 'some-value';

        $form = new Flick([
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeFalse();
    });

    test('honeypot handler is called when the honeypot field contains "0" on POST (M10)', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['website'] = '0'; // a bot that filled the trap with "0"

        $form = new Flick([
            'honeypot' => 'website',
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeTrue();
    });

    test('honeypot handler is called when the honeypot field contains "0" on GET (M10)', function () {
        $handlerCalled = false;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['website'] = '0';

        $form = new Flick([
            'honeypot' => 'website',
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeTrue();
    });
});
