<?php

declare(strict_types=1);

use Flick\Http\ArrayRequest;

describe('ArrayRequest setCookie', function () {
    it('stores cookies that can be retrieved', function () {
        $request = ArrayRequest::createGet();

        $request->setCookie('test_cookie', 'test_value');

        expect($request->cookie('test_cookie'))->toBe('test_value');
    });

    it('stores cookie metadata for testing', function () {
        $request = ArrayRequest::createGet();

        $request->setCookie('test_cookie', 'test_value', [
            'expires' => time() + 3600,
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Strict',
        ]);

        $cookieData = $request->getSetCookie('test_cookie');

        expect($cookieData)->not->toBeNull();
        expect($cookieData['value'])->toBe('test_value');
        expect($cookieData['options']['httponly'])->toBeTrue();
        expect($cookieData['options']['secure'])->toBeTrue();
        expect($cookieData['options']['samesite'])->toBe('Strict');
    });

    it('returns all set cookies', function () {
        $request = ArrayRequest::createGet();

        $request->setCookie('cookie1', 'value1');
        $request->setCookie('cookie2', 'value2');

        $cookies = $request->getSetCookies();

        expect($cookies)->toHaveKey('cookie1');
        expect($cookies)->toHaveKey('cookie2');
        expect($cookies['cookie1']['value'])->toBe('value1');
        expect($cookies['cookie2']['value'])->toBe('value2');
    });

    it('checks if a cookie was set', function () {
        $request = ArrayRequest::createGet();

        expect($request->wasCookieSet('test_cookie'))->toBeFalse();

        $request->setCookie('test_cookie', 'value');

        expect($request->wasCookieSet('test_cookie'))->toBeTrue();
        expect($request->wasCookieSet('other_cookie'))->toBeFalse();
    });

    it('applies default options when not specified', function () {
        $request = ArrayRequest::createGet();

        $request->setCookie('test_cookie', 'value');

        $cookieData = $request->getSetCookie('test_cookie');

        expect($cookieData['options']['path'])->toBe('/');
        expect($cookieData['options']['httponly'])->toBeTrue();
        expect($cookieData['options']['samesite'])->toBe('Strict');
    });

    it('uses secure flag from request context', function () {
        $request = ArrayRequest::createGet()->asSecure();

        $request->setCookie('test_cookie', 'value');

        $cookieData = $request->getSetCookie('test_cookie');

        expect($cookieData['options']['secure'])->toBeTrue();
    });

    it('allows overriding default options', function () {
        $request = ArrayRequest::createGet();

        $request->setCookie('test_cookie', 'value', [
            'path' => '/admin',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $cookieData = $request->getSetCookie('test_cookie');

        expect($cookieData['options']['path'])->toBe('/admin');
        expect($cookieData['options']['httponly'])->toBeFalse();
        expect($cookieData['options']['samesite'])->toBe('Lax');
    });

    it('returns null for non-existent cookie data', function () {
        $request = ArrayRequest::createGet();

        expect($request->getSetCookie('nonexistent'))->toBeNull();
    });

    it('tracks deleted cookies separately from set cookies', function () {
        $request = ArrayRequest::createGet();
        $request->setCookie('existing', 'value');

        // Setting the cookie updates the internal state
        expect($request->wasCookieSet('existing'))->toBeTrue();

        // Deleting the cookie
        $request->deleteCookie('existing');

        expect($request->wasCookieDeleted('existing'))->toBeTrue();
        // Cookie is still in setCookies array (it was set before deletion)
        expect($request->wasCookieSet('existing'))->toBeTrue();
        // But it's no longer in the readable cookies
        expect($request->hasCookie('existing'))->toBeFalse();
    });
});

describe('Cookie clearing workflow', function () {
    it('can clear a cookie by setting with past expiry', function () {
        $request = ArrayRequest::createGet();

        // Initial cookie
        $request->setCookie('remember_me', 'token123', [
            'expires' => time() + 86400,
        ]);

        // Clear by setting empty value with past expiry
        $request->setCookie('remember_me', '', [
            'expires' => time() - 3600,
        ]);

        // The cookie was set twice
        $setData = $request->getSetCookies();
        expect($setData['remember_me']['value'])->toBe('');
        expect($setData['remember_me']['options']['expires'])->toBeLessThan(time());
    });
});
