<?php

declare(strict_types=1);

use Flick\Http\ProxyTrust;

describe('ProxyTrust', function () {
    describe('default heuristic (null list)', function () {
        test('trusts loopback peer', function () {
            expect(ProxyTrust::isTrusted('127.0.0.1', null))->toBeTrue();
        });

        test('trusts private peer', function () {
            expect(ProxyTrust::isTrusted('10.1.2.3', null))->toBeTrue()
                ->and(ProxyTrust::isTrusted('192.168.0.5', null))->toBeTrue()
                ->and(ProxyTrust::isTrusted('172.16.0.1', null))->toBeTrue();
        });

        test('does not trust public peer', function () {
            expect(ProxyTrust::isTrusted('203.0.113.7', null))->toBeFalse();
        });

        test('does not trust invalid address', function () {
            expect(ProxyTrust::isTrusted('not-an-ip', null))->toBeFalse();
        });
    });

    describe('explicit list', function () {
        test('empty list trusts nobody, including private peers', function () {
            expect(ProxyTrust::isTrusted('127.0.0.1', []))->toBeFalse()
                ->and(ProxyTrust::isTrusted('10.1.2.3', []))->toBeFalse();
        });

        test('exact IP match trusts the peer', function () {
            expect(ProxyTrust::isTrusted('203.0.113.7', ['203.0.113.7']))->toBeTrue();
        });

        test('unlisted peer is not trusted, even when private', function () {
            expect(ProxyTrust::isTrusted('10.1.2.3', ['203.0.113.7']))->toBeFalse();
        });

        test('wildcard trusts any peer', function () {
            expect(ProxyTrust::isTrusted('203.0.113.7', ['*']))->toBeTrue()
                ->and(ProxyTrust::isTrusted('10.1.2.3', ['*']))->toBeTrue();
        });

        test('IPv4 CIDR range matches', function () {
            expect(ProxyTrust::isTrusted('10.1.2.3', ['10.0.0.0/8']))->toBeTrue()
                ->and(ProxyTrust::isTrusted('11.0.0.1', ['10.0.0.0/8']))->toBeFalse()
                ->and(ProxyTrust::isTrusted('192.168.1.130', ['192.168.1.128/25']))->toBeTrue()
                ->and(ProxyTrust::isTrusted('192.168.1.1', ['192.168.1.128/25']))->toBeFalse();
        });

        test('IPv6 CIDR range matches', function () {
            expect(ProxyTrust::isTrusted('2001:db8::1', ['2001:db8::/32']))->toBeTrue()
                ->and(ProxyTrust::isTrusted('2001:db9::1', ['2001:db8::/32']))->toBeFalse();
        });

        test('IPv4 peer does not match IPv6 CIDR or vice versa', function () {
            expect(ProxyTrust::isTrusted('10.1.2.3', ['2001:db8::/32']))->toBeFalse()
                ->and(ProxyTrust::isTrusted('2001:db8::1', ['10.0.0.0/8']))->toBeFalse();
        });

        test('malformed entries never match', function () {
            expect(ProxyTrust::isTrusted('10.1.2.3', ['10.0.0.0/oops']))->toBeFalse()
                ->and(ProxyTrust::isTrusted('10.1.2.3', ['banana']))->toBeFalse()
                ->and(ProxyTrust::isTrusted('10.1.2.3', ['10.0.0.0/999']))->toBeFalse();
        });

        test('any matching entry in a mixed list trusts the peer', function () {
            $list = ['203.0.113.7', '10.0.0.0/8'];

            expect(ProxyTrust::isTrusted('10.9.9.9', $list))->toBeTrue()
                ->and(ProxyTrust::isTrusted('203.0.113.7', $list))->toBeTrue()
                ->and(ProxyTrust::isTrusted('8.8.8.8', $list))->toBeFalse();
        });
    });

    describe('isPrivateOrReserved', function () {
        test('recognizes private and reserved addresses', function () {
            expect(ProxyTrust::isPrivateOrReserved('127.0.0.1'))->toBeTrue()
                ->and(ProxyTrust::isPrivateOrReserved('10.0.0.1'))->toBeTrue()
                ->and(ProxyTrust::isPrivateOrReserved('203.0.113.7'))->toBeFalse()
                ->and(ProxyTrust::isPrivateOrReserved('garbage'))->toBeFalse();
        });
    });
});
