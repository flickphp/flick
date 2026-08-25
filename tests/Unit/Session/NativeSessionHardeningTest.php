<?php

declare(strict_types=1);

namespace Flick\Session;

use Flick\Http\ArrayRequest;

/**
 * Session hardening: the cookie params Flick sets before starting a session,
 * and the id regeneration that defends against session fixation.
 *
 * regenerateId() had never run. It is what stops an attacker who planted a
 * session id before login from still holding a valid session after it — the
 * classic fixation attack — so a guard that quietly stopped firing would be
 * invisible until someone exploited it.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/configuration.md lines
 * 360-366, "Hardened session cookies" — HttpOnly, Secure on HTTPS,
 * SameSite=Strict, applied only when Flick starts the session itself.
 *
 * NativeSession calls the session_*() functions unqualified from inside this
 * namespace. The stubs below only intercept while a test arms them, so every
 * other test that touches a real session is unaffected.
 */
final class SessionSpy
{
    public static bool $intercept = false;

    public static int $status = PHP_SESSION_NONE;

    /** @var array<int, bool> */
    public static array $regenerateCalls = [];

    /** @var array<int, array> */
    public static array $cookieParams = [];

    public static bool $started = false;

    public static function arm(int $status): void
    {
        self::$intercept = true;
        self::$status = $status;
        self::$regenerateCalls = [];
        self::$cookieParams = [];
        self::$started = false;
    }

    public static function disarm(): void
    {
        self::$intercept = false;
    }
}

function session_status(): int
{
    return SessionSpy::$intercept ? SessionSpy::$status : \session_status();
}

function session_regenerate_id(bool $deleteOldSession = false): bool
{
    if (! SessionSpy::$intercept) {
        return \session_regenerate_id($deleteOldSession);
    }

    SessionSpy::$regenerateCalls[] = $deleteOldSession;

    return true;
}

function session_set_cookie_params(array $options): bool
{
    if (! SessionSpy::$intercept) {
        return \session_set_cookie_params($options);
    }

    SessionSpy::$cookieParams[] = $options;

    return true;
}

function session_start(array $options = []): bool
{
    if (! SessionSpy::$intercept) {
        return \session_start($options);
    }

    SessionSpy::$started = true;

    return true;
}

afterEach(function () {
    SessionSpy::disarm();
});

/*
|--------------------------------------------------------------------------
| Session fixation defence
|--------------------------------------------------------------------------
*/

it('regenerates the session id when a session is running', function () {
    SessionSpy::arm(PHP_SESSION_ACTIVE);

    (new NativeSession(new ArrayRequest, false))->regenerateId();

    expect(SessionSpy::$regenerateCalls)->toHaveCount(1);
});

it('keeps the old session data by default when regenerating', function () {
    SessionSpy::arm(PHP_SESSION_ACTIVE);

    (new NativeSession(new ArrayRequest, false))->regenerateId();

    expect(SessionSpy::$regenerateCalls[0])->toBeFalse();
});

it('deletes the old session when asked to', function () {
    SessionSpy::arm(PHP_SESSION_ACTIVE);

    (new NativeSession(new ArrayRequest, false))->regenerateId(true);

    expect(SessionSpy::$regenerateCalls[0])->toBeTrue();
});

it('does nothing when no session is running', function () {
    // Regenerating without an active session would emit a PHP warning and
    // achieve nothing, so the guard has to hold.
    SessionSpy::arm(PHP_SESSION_NONE);

    (new NativeSession(new ArrayRequest, false))->regenerateId();

    expect(SessionSpy::$regenerateCalls)->toBe([]);
});

it('does nothing when sessions are disabled', function () {
    SessionSpy::arm(PHP_SESSION_DISABLED);

    (new NativeSession(new ArrayRequest, false))->regenerateId();

    expect(SessionSpy::$regenerateCalls)->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Hardened session cookie — configuration.md lines 360-366
|--------------------------------------------------------------------------
*/

it('marks the session cookie httponly before starting', function () {
    SessionSpy::arm(PHP_SESSION_NONE);

    (new NativeSession(new ArrayRequest, false))->start();

    expect(SessionSpy::$cookieParams[0]['httponly'])->toBeTrue();
});

it('marks the session cookie samesite strict before starting', function () {
    SessionSpy::arm(PHP_SESSION_NONE);

    (new NativeSession(new ArrayRequest, false))->start();

    expect(SessionSpy::$cookieParams[0]['samesite'])->toBe('Strict');
});

it('does not mark the session cookie secure over plain http', function () {
    SessionSpy::arm(PHP_SESSION_NONE);

    (new NativeSession(new ArrayRequest, false))->start();

    expect(SessionSpy::$cookieParams[0]['secure'])->toBeFalse();
});

it('starts the session after setting the cookie params', function () {
    SessionSpy::arm(PHP_SESSION_NONE);

    (new NativeSession(new ArrayRequest, false))->start();

    expect(SessionSpy::$cookieParams)->toHaveCount(1)
        ->and(SessionSpy::$started)->toBeTrue();
});

it('leaves an already-running session alone', function () {
    // PHP only accepts these params before session_start(), and stomping on a
    // session a framework already started would be worse than doing nothing.
    SessionSpy::arm(PHP_SESSION_ACTIVE);

    (new NativeSession(new ArrayRequest, false))->start();

    expect(SessionSpy::$cookieParams)->toBe([])
        ->and(SessionSpy::$started)->toBeFalse();
});
