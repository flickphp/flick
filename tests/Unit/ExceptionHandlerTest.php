<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Session\ArraySession;

describe('Exception Handler Chaining', function () {
    beforeEach(function () {
        // Save the current exception handler
        $this->originalHandler = snapshotExceptionHandler();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];

        // Pop the handlers set during the test — Flick's constructor pushes
        // one, and a test may have pushed its own — back to the original.
        unwindExceptionHandlersTo($this->originalHandler);
    });

    test('previous exception handler is called when exception is thrown', function () {
        $previousHandlerCalled = false;
        $receivedException = null;

        // Register a custom handler BEFORE creating Flick
        set_exception_handler(function ($exception) use (&$previousHandlerCalled, &$receivedException) {
            $previousHandlerCalled = true;
            $receivedException = $exception;
        });

        // Ensure no default request is set (standalone mode), so Flick registers its handler
        Flick::resetDefaultRequest();

        $form = new Flick([
            'csrf' => false,
            'onException' => fn () => null,
        ]);

        // Trigger the global exception handler directly
        $testException = new RuntimeException('Test exception for chaining');

        $form->globalExceptionHandler($testException);

        expect($previousHandlerCalled)->toBeTrue();
        expect($receivedException)->toBe($testException);
        expect($receivedException->getMessage())->toBe('Test exception for chaining');
    });

    test('exception handler does not error when no previous handler exists', function () {
        // Clear any existing handler
        set_exception_handler(null);

        Flick::resetDefaultRequest();

        $form = new Flick([
            'csrf' => false,
            'onException' => fn () => null,
        ]);

        // Should not throw or error when no previous handler exists
        $form->globalExceptionHandler(new RuntimeException('Test exception'));

        // If we reach here, chaining handled the null case gracefully
        expect(true)->toBeTrue();

        // pop Flick's handler and the null pushed above ourselves: afterEach's
        // unwind stops at a null active handler, so it cannot pop past the null
        restore_exception_handler();
        restore_exception_handler();
    });
});

describe('Exception Handler Ownership', function () {
    beforeEach(function () {
        $this->originalHandler = snapshotExceptionHandler();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
    });

    afterEach(function () {
        unwindExceptionHandlersTo($this->originalHandler);
        Flick::resetDefaultSession();
    });

    test('any number of standalone forms install exactly one global handler', function () {
        Flick::resetDefaultRequest();

        new Flick(['csrf' => false, 'echo' => false]);
        $afterFirst = snapshotExceptionHandler();

        new Flick(['csrf' => false, 'echo' => false]);
        $afterSecond = snapshotExceptionHandler();

        expect($afterFirst)->not->toBe($this->originalHandler)
            ->and($afterSecond)->toBe($afterFirst);

        // one pop returns to the pre-Flick handler; a stacked copy would not
        restore_exception_handler();
        expect(snapshotExceptionHandler())->toBe($this->originalHandler);
    });

    test('a default session alone does not suppress the standalone install', function () {
        Flick::resetDefaultRequest();
        Flick::setDefaultSession(new ArraySession);

        new Flick(['csrf' => false, 'echo' => false]);

        expect(snapshotExceptionHandler())->not->toBe($this->originalHandler);
    });
});
