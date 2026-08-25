<?php

declare(strict_types=1);

use Flick\Flick;

beforeEach(function () {
    // Reset the static generator before each test
    Flick::resetDefaultCsrfTokenGenerator();

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
    Flick::resetDefaultCsrfTokenGenerator();
    $_POST = [];
    $_GET = [];
});

it('can set and get the default CSRF token generator', function () {
    expect(Flick::getDefaultCsrfTokenGenerator())->toBeNull();

    $generator = fn () => 'test-token';
    Flick::setDefaultCsrfTokenGenerator($generator);

    expect(Flick::getDefaultCsrfTokenGenerator())->toBe($generator);
});

it('can reset the default CSRF token generator', function () {
    $generator = fn () => 'test-token';
    Flick::setDefaultCsrfTokenGenerator($generator);

    expect(Flick::getDefaultCsrfTokenGenerator())->not->toBeNull();

    Flick::resetDefaultCsrfTokenGenerator();

    expect(Flick::getDefaultCsrfTokenGenerator())->toBeNull();
});

it('uses custom CSRF token generator when csrf config is false', function () {
    $tokenValue = 'custom-laravel-token-abc123';
    Flick::setDefaultCsrfTokenGenerator(fn () => $tokenValue);

    $form = new Flick(['csrf' => false, 'echo' => false]);
    $html = $form->open('/submit');

    expect($html)->toContain('name="_token"');
    expect($html)->toContain('value="'.$tokenValue.'"');
});

it('outputs no token when csrf is false and no generator is set', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);
    $html = $form->open('/submit');

    expect($html)->not->toContain('name="_token"');
});

it('uses custom generator when csrf config is not set (null)', function () {
    $tokenValue = 'custom-laravel-token-abc123';
    Flick::setDefaultCsrfTokenGenerator(fn () => $tokenValue);

    $form = new Flick(['echo' => false]); // No csrf config (null)
    $html = $form->open('/submit');

    // Should use the custom generator token
    expect($html)->toContain('name="_token"');
    expect($html)->toContain('value="'.$tokenValue.'"');
    // Should NOT have timestamp format (no pipe)
    expect($html)->not->toContain($tokenValue.'|');
});

it('uses Flick native CSRF when csrf config is explicitly true', function () {
    $tokenValue = 'custom-laravel-token-abc123';
    Flick::setDefaultCsrfTokenGenerator(fn () => $tokenValue);

    $form = new Flick(['csrf' => true, 'echo' => false]); // Explicitly enable native CSRF
    $html = $form->open('/submit');

    // Should have a token, but not our custom one
    expect($html)->toContain('name="_token"');
    // Native tokens are raw hex (no pipe — expiration is stored server-side)
    expect($html)->not->toContain('|');
    // Should NOT contain our custom token value directly
    expect($html)->not->toContain('value="'.$tokenValue.'"');
});

it('uses Flick native CSRF when csrf config is an integer timeout', function () {
    $tokenValue = 'custom-laravel-token-abc123';
    Flick::setDefaultCsrfTokenGenerator(fn () => $tokenValue);

    $form = new Flick(['csrf' => 7200, 'echo' => false]); // Custom timeout enables native CSRF
    $html = $form->open('/submit');

    // Should have a token with native format (no pipe — expiration is stored server-side)
    expect($html)->toContain('name="_token"');
    expect($html)->not->toContain('|');
    // Should NOT contain our custom token value directly
    expect($html)->not->toContain('value="'.$tokenValue.'"');
});

it('does not bypass CSRF validation when the generator returns null', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => null);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    // _id must match the default form id or submitted() fails before CSRF.
    $_POST = ['name' => 'Tim', '_id' => 'myForm'];

    $form = new Flick(['echo' => false]);

    // When the generator yields null, rendering falls back to issuing a
    // native token — so validation must actually validate. A POST without
    // any token cannot pass just because a generator is registered.
    expect($form->submitted())->toBeFalse();
});

it('properly escapes CSRF token value in HTML', function () {
    $tokenWithSpecialChars = '<script>alert("xss")</script>';
    Flick::setDefaultCsrfTokenGenerator(fn () => $tokenWithSpecialChars);

    $form = new Flick(['csrf' => false, 'echo' => false]);
    $html = $form->open('/submit');

    // The token should be HTML-escaped
    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script&gt;');
});
