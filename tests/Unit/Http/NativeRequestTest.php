<?php

declare(strict_types=1);

use Flick\Http\NativeRequest;

describe('NativeRequest proxy trust', function () {
    beforeEach(function () {
        $this->serverBackup = $_SERVER;

        unset(
            $_SERVER['HTTPS'],
            $_SERVER['SERVER_PORT'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_X_FORWARDED_PROTO'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
        );
    });

    afterEach(function () {
        $_SERVER = $this->serverBackup;
    });

    describe('isSecure', function () {
        test('HTTPS server variable wins regardless of proxy trust', function () {
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

            expect((new NativeRequest([]))->isSecure())->toBeTrue();
        });

        test('forwarded proto from a private peer is honored by default', function () {
            $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest)->isSecure())->toBeTrue();
        });

        test('forwarded proto from a public peer is ignored by default', function () {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest)->isSecure())->toBeFalse();
        });

        test('strict mode ignores forwarded proto even from a private peer', function () {
            $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest([]))->isSecure())->toBeFalse();
        });

        test('forwarded proto from a listed public peer is honored', function () {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest(['203.0.113.7']))->isSecure())->toBeTrue();
        });

        test('forwarded proto from an unlisted private peer is ignored when a list is set', function () {
            $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest(['203.0.113.7']))->isSecure())->toBeFalse();
        });

        test('CIDR entries match', function () {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest(['203.0.113.0/24']))->isSecure())->toBeTrue();
        });

        test('wildcard trusts any peer', function () {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            expect((new NativeRequest(['*']))->isSecure())->toBeTrue();
        });

        test('non-https forwarded proto is never secure', function () {
            $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';

            expect((new NativeRequest)->isSecure())->toBeFalse();
        });
    });

    describe('ip', function () {
        test('forwarded-for from a private peer is honored by default', function () {
            $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';

            expect((new NativeRequest)->ip())->toBe('198.51.100.9');
        });

        test('forwarded-for from a public peer is ignored by default', function () {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';

            expect((new NativeRequest)->ip())->toBe('203.0.113.7');
        });

        test('strict mode ignores forwarded-for even from a private peer', function () {
            $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';

            expect((new NativeRequest([]))->ip())->toBe('10.0.0.5');
        });

        test('forwarded-for from a listed public peer is honored', function () {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';

            expect((new NativeRequest(['203.0.113.0/24']))->ip())->toBe('198.51.100.9');
        });
    });
});
