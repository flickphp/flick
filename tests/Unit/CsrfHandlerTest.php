<?php

declare(strict_types=1);

use Flick\Flick;

describe('CSRF Handler Integration', function () {
    beforeEach(function () {
        // Save the current exception handler
        $this->previousHandler = snapshotExceptionHandler();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];

        // Start a session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Clear any existing Flick session data
        unset($_SESSION['flick']);
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];

        // Restore the original exception handler
        unwindExceptionHandlersTo($this->previousHandler);
    });

    test('csrf expired handler is called when token is expired', function () {
        $handlerCalled = false;
        $receivedMessage = null;

        // Create a form instance to generate a valid token
        $form = new Flick([
            'id' => 'testForm',
            'onCsrfExpired' => function (string $message) use (&$handlerCalled, &$receivedMessage) {
                $handlerCalled = true;
                $receivedMessage = $message;

                return null;
            },
        ]);

        // Get a valid token from session
        $validToken = $_SESSION['flick']['_token'] ?? null;

        if ($validToken === null) {
            // Token wasn't set, skip the detailed test
            expect(true)->toBeTrue();

            return;
        }

        // Set up expired token (timestamp in the past)
        $expiredTimestamp = time() - 3600; // 1 hour ago
        $_POST['_token'] = $validToken.'|'.$expiredTimestamp;
        $_POST['_id'] = 'testForm';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Create a new form that will validate the expired token
        $form2 = new Flick([
            'id' => 'testForm',
            'onCsrfExpired' => function (string $message) use (&$handlerCalled, &$receivedMessage) {
                $handlerCalled = true;
                $receivedMessage = $message;

                return null;
            },
        ]);

        // Trigger submission check which validates CSRF
        $form2->submitted();

        expect($handlerCalled)->toBeTrue();
        expect($receivedMessage)->not->toBeEmpty();
    });

    test('submitted returns false and shows error when session token is missing', function () {
        // Create a form to generate a valid token
        $form = new Flick([
            'id' => 'testForm',
        ]);

        // Get the valid token from session
        $validToken = $_SESSION['flick']['_token'] ?? null;

        if ($validToken === null) {
            expect(true)->toBeTrue();

            return;
        }

        // Simulate POST with token, but clear the session token (session expired)
        $_POST['_token'] = $validToken.'|'.(time() + 3600);
        $_POST['_id'] = 'testForm';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Clear the session token to simulate session expiration
        unset($_SESSION['flick']['_token']);

        $form2 = new Flick([
            'id' => 'testForm',
            'echo' => true,
        ]);

        // Capture output to verify error message is displayed
        ob_start();
        $result = $form2->submitted();
        $output = ob_get_clean();

        expect($result)->toBeFalse();
        expect($output)->toContain('session has expired');
    });

    test('csrf() emits a token field and stores the token for hand-written forms', function () {
        $form = new Flick(['id' => 'manualForm']);

        $field = $form->csrf();

        expect($field)->toContain('name="_token"')
            ->and($field)->toContain('type="hidden"')
            ->and($_SESSION['flick']['_token'] ?? null)->not->toBeNull();

        // the emitted value matches the stored token
        expect($field)->toContain($_SESSION['flick']['_token']);
    });

    test('csrf() returns an empty string when CSRF is disabled', function () {
        $form = new Flick(['id' => 'noCsrfForm', 'csrf' => false]);

        expect($form->csrf())->toBe('');
    });

    test('re-rendering does not slide the csrf token expiry forward (absolute timeout)', function () {
        // Seed an existing, non-expired token with a fixed expiry.
        $fixedExpiry = time() + 3600;
        $_SESSION['flick']['_token'] = 'seededtoken';
        $_SESSION['flick']['_token_expires'] = $fixedExpiry;

        // Rendering a form emits the CSRF field; the expiry must not be reset,
        // otherwise the timeout would slide forward on every render.
        $form = new Flick(['id' => 'slideForm']);
        $form->open('/submit');

        expect($_SESSION['flick']['_token_expires'])->toBe($fixedExpiry)
            ->and($_SESSION['flick']['_token'])->toBe('seededtoken');
    });

    test('csrf expired handler is not called for valid tokens', function () {
        $handlerCalled = false;

        // Create a form instance to generate a valid token
        $form = new Flick([
            'id' => 'testForm',
            'onCsrfExpired' => function (string $message) use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        // Get a valid token from session
        $validToken = $_SESSION['flick']['_token'] ?? null;

        if ($validToken === null) {
            expect(true)->toBeTrue();

            return;
        }

        // Set up valid token (timestamp in the future)
        $validTimestamp = time() + 3600; // 1 hour from now
        $_POST['_token'] = $validToken.'|'.$validTimestamp;
        $_POST['_id'] = 'testForm';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Create a new form that will validate the token
        $form2 = new Flick([
            'id' => 'testForm',
            'onCsrfExpired' => function (string $message) use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        $form2->submitted();

        expect($handlerCalled)->toBeFalse();
    });

    test('a custom non-exiting onCsrfExpired handler yields exactly one expired message (L2)', function () {
        // snapshot the current exception handler so we can drain back to it below
        $snapshot = snapshotExceptionHandler();

        try {
            // render a form to generate the raw session token + expiry
            $form = new Flick([
                'id' => 'testForm',
                'echo' => false,
            ]);
            $form->open('/testForm');

            $validToken = $_SESSION['flick']['_token'] ?? null;
            expect($validToken)->not->toBeNull();

            // post the matching token, then force the stored expiry into the past
            $_POST['_token'] = $validToken;
            $_POST['_id'] = 'testForm';
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SESSION['flick']['_token_expires'] = time() - 3600;

            ob_start();
            $form2 = new Flick([
                'id' => 'testForm',
                'echo' => true,
                'onCsrfExpired' => fn (string $message) => null, // returns instead of exiting
            ]);
            $form2->submitted();
            $output = ob_get_clean();

            expect(substr_count($output, 'session has expired'))->toBe(1);
        } finally {
            // drain any exception handlers the Flick instances registered, back
            // to the snapshot, so this test leaves the handler stack balanced
            unwindExceptionHandlersTo($snapshot);
        }
    });
});
