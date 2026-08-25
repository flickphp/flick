<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Strict CSRF against a framework token
|--------------------------------------------------------------------------
|
| With a framework token generator installed, Flick normally trusts that the
| framework's own middleware validated the posted token. That is right for a
| route inside Laravel's `web` group and wrong for one outside it, where
| nothing checks the token at all.
|
| 'csrf' => 'strict' makes Flick do the comparison itself. It is opt-in on
| purpose: a client that sends the token only as a header — Axios sends
| X-XSRF-TOKEN automatically — posts no _token field, and rejecting that would
| fail the submission with nothing on screen to explain why.
|
*/

beforeEach(function () {
    Flick::resetDefaultCsrfTokenGenerator();
});

afterEach(function () {
    Flick::resetDefaultCsrfTokenGenerator();
});

function strictForm(array $post, array|string|bool|null $csrf): Flick
{
    return new Flick([
        'request' => new ArrayRequest([
            'post' => $post,
            'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/contact'],
        ]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => $csrf,
    ]);
}

it('accepts a posted token that matches the framework token', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm', '_token' => 'framework-token'], 'strict');

    expect($form->submitted())->toBeTrue()
        ->and($form->getErrors())->toBe([]);
});

it('rejects a posted token that does not match', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm', '_token' => 'forged'], 'strict');

    expect($form->submitted())->toBeFalse()
        ->and($form->hasError('_token'))->toBeTrue();
});

it('rejects a submission carrying no token at all', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm'], 'strict');

    expect($form->submitted())->toBeFalse()
        ->and($form->hasError('_token'))->toBeTrue();
});

it('rejects an array token instead of handing it to hash_equals', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm', '_token' => ['forged']], 'strict');

    expect($form->submitted())->toBeFalse();
});

/**
 * Render a strict form against a shared session so the native token it issues
 * can be posted back to a second instance, exactly as a browser would.
 */
function strictRoundTrip(callable $generator, string $postedToken): Flick
{
    Flick::setDefaultCsrfTokenGenerator($generator);

    $session = new ArraySession;

    $rendered = new Flick([
        'request' => new ArrayRequest(['server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/contact']]),
        'session' => $session,
        'echo' => false,
        'csrf' => 'strict',
    ]);
    $rendered->csrf();

    $token = $postedToken === '' ? (string) $session->getValue('_token') : $postedToken;

    return new Flick([
        'request' => new ArrayRequest([
            'post' => ['_id' => 'myForm', '_token' => $token],
            'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/contact'],
        ]),
        'session' => $session,
        'echo' => false,
        'csrf' => 'strict',
    ]);
}

it('accepts the native token it rendered when the generator yields null', function () {
    // A generator that exists but returns null (Laravel's csrf_token() outside
    // a session context) makes rendering fall back to a native token. Strict
    // validation used to gate on the closure existing rather than on a token
    // coming back, so it rejected every POST forever while the valid native
    // token sat in the payload.
    $form = strictRoundTrip(fn () => null, '');

    expect($form->submitted())->toBeTrue()
        ->and($form->getErrors())->toBe([]);
});

it('still rejects a forged token when the generator yields null', function () {
    $form = strictRoundTrip(fn () => null, 'forged');

    expect($form->submitted())->toBeFalse()
        ->and($form->hasError('_token'))->toBeTrue();
});

it('falls back to native csrf when strict is set without a generator', function () {
    // No framework generator: 'strict' has no framework token to compare
    // against, so the native session-token path decides, exactly as before.
    $form = strictForm(['_id' => 'myForm', '_token' => 'anything'], 'strict');

    expect($form->submitted())->toBeFalse();
});

it('leaves csrf => false switched off', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm'], false);

    expect($form->submitted())->toBeTrue()
        ->and($form->getErrors())->toBe([]);
});

it('never invokes the generator for a csrf-disabled form', function () {
    $calls = 0;
    Flick::setDefaultCsrfTokenGenerator(function () use (&$calls) {
        $calls++;

        return 'framework-token';
    });

    strictForm(['_id' => 'myForm'], false)->submitted();

    expect($calls)->toBe(0);
});

it('rejects a strict submission with no token anywhere when the generator yields null', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => null);

    $form = strictForm(['_id' => 'myForm'], 'strict');

    expect($form->submitted())->toBeFalse();
});

it('still trusts the framework when csrf is unset', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm'], null);

    expect($form->submitted())->toBeTrue()
        ->and($form->getErrors())->toBe([]);
});

it('renders the framework token in strict mode', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'framework-token');

    $form = strictForm(['_id' => 'myForm', '_token' => 'framework-token'], 'strict');

    expect($form->csrf())->toContain('value="framework-token"');
});
