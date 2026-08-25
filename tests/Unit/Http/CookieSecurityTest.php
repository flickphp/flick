<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * The security flags Flick puts on every cookie it sets.
 *
 * These are the defaults an application inherits without asking, so a
 * regression here silently strips protection from the auth remember-me cookie
 * and anything else built on setCookie(). The docs commit to them twice:
 * guide/configuration.md line 363 ("HttpOnly, Secure on HTTPS, SameSite=Strict")
 * and services/auth.md line 408 ("Secure Cookie Defaults: HttpOnly, Secure,
 * SameSite=Strict").
 *
 * NativeRequest calls setcookie() unqualified from inside this namespace, so
 * PHP looks for Flick\Http\setcookie() before the global one. The stub below
 * captures the arguments instead of emitting a header, which keeps the whole
 * thing in-process — no output buffering, no "headers already sent".
 */
final class SetCookieSpy
{
    /** @var array<int, array{name: string, value: string, options: array}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    /** @return array{name: string, value: string, options: array} */
    public static function lastCall(): array
    {
        return self::$calls[array_key_last(self::$calls)];
    }

    public static function lastOptions(): array
    {
        return self::lastCall()['options'];
    }
}

function setcookie(string $name, string $value = '', array|int $options = []): bool
{
    SetCookieSpy::$calls[] = [
        'name' => $name,
        'value' => $value,
        'options' => is_array($options) ? $options : ['expires' => $options],
    ];

    return true;
}

beforeEach(function () {
    SetCookieSpy::reset();

    $this->serverBackup = $_SERVER;
    $this->cookieBackup = $_COOKIE;
    $_COOKIE = [];

    // Default to a plain HTTP request; the HTTPS cases opt in explicitly.
    unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
});

afterEach(function () {
    $_SERVER = $this->serverBackup;
    $_COOKIE = $this->cookieBackup;
});

/*
|--------------------------------------------------------------------------
| setCookie() defaults — configuration.md line 363, auth.md line 408
|--------------------------------------------------------------------------
*/

it('marks every cookie httponly so javascript cannot read it', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['httponly'])->toBeTrue();
});

it('marks every cookie samesite strict', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['samesite'])->toBe('Strict');
});

it('scopes the cookie to the whole site by default', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['path'])->toBe('/');
});

it('sets a session cookie by default rather than a persistent one', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['expires'])->toBe(0);
});

it('passes the name and value through untouched', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    $call = SetCookieSpy::lastCall();

    expect($call['name'])->toBe('flick_remember')
        ->and($call['value'])->toBe('token-value');
});

/*
|--------------------------------------------------------------------------
| The Secure flag follows the connection
|--------------------------------------------------------------------------
*/

it('marks the cookie secure over https', function () {
    $_SERVER['HTTPS'] = 'on';

    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['secure'])->toBeTrue();
});

it('marks the cookie secure on port 443', function () {
    $_SERVER['SERVER_PORT'] = 443;

    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['secure'])->toBeTrue();
});

it('does not mark the cookie secure over plain http', function () {
    // Secure on a plain connection would stop the cookie being sent at all.
    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['secure'])->toBeFalse();
});

it('ignores a forwarded-proto header from an untrusted client', function () {
    // The header is client-supplied; trusting it blindly would let anyone claim
    // HTTPS. With no trusted proxies configured it must not count.
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

    (new NativeRequest)->setCookie('flick_remember', 'token-value');

    expect(SetCookieSpy::lastOptions()['secure'])->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Caller overrides
|--------------------------------------------------------------------------
*/

it('lets the caller override a default', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value', ['expires' => 1893456000]);

    expect(SetCookieSpy::lastOptions()['expires'])->toBe(1893456000);
});

it('keeps the remaining hardened defaults when one is overridden', function () {
    (new NativeRequest)->setCookie('flick_remember', 'token-value', ['path' => '/admin']);

    $options = SetCookieSpy::lastOptions();

    expect($options['path'])->toBe('/admin')
        ->and($options['httponly'])->toBeTrue()
        ->and($options['samesite'])->toBe('Strict');
});

/*
|--------------------------------------------------------------------------
| Reading back
|--------------------------------------------------------------------------
*/

it('makes the cookie readable straight after it is set', function () {
    $request = new NativeRequest;
    $request->setCookie('flick_remember', 'token-value');

    expect($request->cookie('flick_remember'))->toBe('token-value')
        ->and($request->hasCookie('flick_remember'))->toBeTrue();
});

it('returns the default for a cookie that was never set', function () {
    $request = new NativeRequest;

    expect($request->cookie('nope', 'fallback'))->toBe('fallback')
        ->and($request->hasCookie('nope'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| deleteCookie()
|--------------------------------------------------------------------------
*/

it('expires a deleted cookie in the past', function () {
    $_COOKIE['flick_remember'] = 'token-value';

    (new NativeRequest)->deleteCookie('flick_remember');

    expect(SetCookieSpy::lastOptions()['expires'])->toBeLessThan(time());
});

it('keeps the hardened flags when deleting', function () {
    $_COOKIE['flick_remember'] = 'token-value';

    (new NativeRequest)->deleteCookie('flick_remember');

    $options = SetCookieSpy::lastOptions();

    expect($options['httponly'])->toBeTrue()
        ->and($options['samesite'])->toBe('Strict')
        ->and($options['path'])->toBe('/');
});

it('blanks the value when deleting', function () {
    $_COOKIE['flick_remember'] = 'token-value';

    (new NativeRequest)->deleteCookie('flick_remember');

    expect(SetCookieSpy::lastCall()['value'])->toBe('');
});

it('stops reporting the cookie once it is deleted', function () {
    $_COOKIE['flick_remember'] = 'token-value';

    $request = new NativeRequest;
    $request->deleteCookie('flick_remember');

    expect($request->hasCookie('flick_remember'))->toBeFalse();
});

it('does nothing when asked to delete a cookie that is not there', function () {
    (new NativeRequest)->deleteCookie('never-existed');

    expect(SetCookieSpy::$calls)->toBe([]);
});
