<?php

declare(strict_types=1);

use Flick\Flick;

// Bug #17 — a non-string _token (attacker posts _token[]=x) must be rejected,
// not handed to hash_equals(), which throws a TypeError.
describe('CSRF array-token guard (#17)', function () {
    beforeEach(function () {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        $_GET = [];

        // A valid session token is present, so the only thing that makes the
        // request invalid is the array-shaped posted token.
        $_SESSION['flick'] = ['_token' => 'valid-token', '_token_expires' => time() + 3600];
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];
        unset($_SESSION['flick']);
    });

    test('submitted() returns false instead of crashing on an array _token', function () {
        $_POST['_id'] = 'tokenForm';
        $_POST['_token'] = ['x'];

        $form = new Flick(['id' => 'tokenForm']);

        $result = $form->submitted();

        expect($result)->toBeFalse();
    });
});
