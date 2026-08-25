<?php

declare(strict_types=1);

use Flick\Flick;

/*
|--------------------------------------------------------------------------
| One table for the CSRF policy
|--------------------------------------------------------------------------
|
| (csrf config x generator x generator return) must resolve to the same
| emit/check pair in Build::addCsrfToken() and checkForAndValidateCsrfToken().
| The two used to interpret the config independently and drifted: 'strict'
| plus a null-yielding generator once rejected every POST while a valid
| native token sat in the payload (CHANGELOG), and an empty-string generator
| return counted as "present" for the trust path but "absent" for strict.
| Both sides now share Flick::resolveCsrfTokenSource().
|
*/

beforeEach(function () {
    Flick::resetDefaultCsrfTokenGenerator();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['flick']);
});

afterEach(function () {
    Flick::resetDefaultCsrfTokenGenerator();
    $_POST = [];
    $_GET = [];
});

/** Render a form and return the emitted _token value, or null when none was emitted. */
function renderedCsrfToken(array $config): ?string
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $html = (new Flick($config + ['echo' => false]))->create('Name');

    return preg_match('/name="_token" value="([^"]*)"/', $html, $m) ? $m[1] : null;
}

/** POST with the given _token (null posts none) and report what submitted() decides. */
function csrfCheckPasses(array $config, ?string $postedToken): bool
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'name' => 'Gern'];

    if ($postedToken !== null) {
        $_POST['_token'] = $postedToken;
    }

    return (new Flick($config + ['echo' => false]))->submitted();
}

it('csrf false with no generator emits nothing and skips the check', function () {
    expect(renderedCsrfToken(['csrf' => false]))->toBeNull()
        ->and(csrfCheckPasses(['csrf' => false], null))->toBeTrue();
});

it('csrf false with a generator emits the framework token and still skips the check', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'fw-tok');

    expect(renderedCsrfToken(['csrf' => false]))->toBe('fw-tok')
        ->and(csrfCheckPasses(['csrf' => false], null))->toBeTrue();
});

it('a generator with csrf unset emits the framework token and trusts the middleware', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'fw-tok');

    expect(renderedCsrfToken([]))->toBe('fw-tok')
        ->and(csrfCheckPasses([], null))->toBeTrue();
});

it('strict emits the framework token and compares the posted one against it', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'fw-tok');

    expect(renderedCsrfToken(['csrf' => 'strict']))->toBe('fw-tok')
        ->and(csrfCheckPasses(['csrf' => 'strict'], 'fw-tok'))->toBeTrue()
        ->and(csrfCheckPasses(['csrf' => 'strict'], 'wrong'))->toBeFalse()
        ->and(csrfCheckPasses(['csrf' => 'strict'], null))->toBeFalse();
});

it('explicit native csrf ignores the generator on both sides', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => 'fw-tok');

    $token = renderedCsrfToken(['csrf' => true]);

    expect($token)->not->toBeNull()
        ->and($token)->not->toBe('fw-tok')
        ->and(csrfCheckPasses(['csrf' => true], $token))->toBeTrue()
        ->and(csrfCheckPasses(['csrf' => true], 'fw-tok'))->toBeFalse();
});

it('a generator yielding an empty string is absent: both sides fall back to native', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => '');

    $token = renderedCsrfToken([]);

    // absent means a real native token is issued, not an empty framework one
    expect($token)->not->toBeNull()
        ->and($token)->not->toBe('')
        ->and(csrfCheckPasses([], $token))->toBeTrue()
        ->and(csrfCheckPasses([], null))->toBeFalse();
});

it('strict with an empty-string generator validates natively instead of rejecting every POST', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => '');

    $token = renderedCsrfToken(['csrf' => 'strict']);

    expect($token)->not->toBeNull()
        ->and($token)->not->toBe('')
        ->and(csrfCheckPasses(['csrf' => 'strict'], $token))->toBeTrue();
});

it('a generator yielding null is absent: both sides fall back to native', function () {
    Flick::setDefaultCsrfTokenGenerator(fn () => null);

    $token = renderedCsrfToken([]);

    expect($token)->not->toBeNull()
        ->and(csrfCheckPasses([], $token))->toBeTrue()
        ->and(csrfCheckPasses([], null))->toBeFalse();
});
