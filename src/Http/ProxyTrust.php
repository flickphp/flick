<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Decides whether a connecting peer is a trusted proxy, i.e. whether its
 * forwarded headers (X-Forwarded-Proto, X-Forwarded-For) may be honored.
 *
 * Trust model, shared by NativeRequest and ArrayRequest:
 * - No list configured (null): trust private/loopback peers only. A request
 *   that genuinely arrived through a local reverse proxy has a private peer
 *   address; a direct public client cannot fake one, because the peer address
 *   comes from the TCP connection, not a header.
 * - List configured: trust exactly the listed peers. Entries may be single
 *   IPs, CIDR ranges ('10.0.0.0/8'), or '*' for any peer. An empty list
 *   trusts nobody — forwarded headers are always ignored (strict mode).
 */
final class ProxyTrust
{
    /**
     * True when the peer may speak for the client via forwarded headers.
     *
     * @param  string  $peer  The connecting address (REMOTE_ADDR)
     * @param  array|null  $trustedProxies  Configured proxy list, or null for the default heuristic
     */
    public static function isTrusted(string $peer, ?array $trustedProxies): bool
    {
        if ($trustedProxies === null) {
            return self::isPrivateOrReserved($peer);
        }

        foreach ($trustedProxies as $entry) {
            if (self::matches($peer, (string) $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the address is a valid IP inside a private or reserved (incl. loopback) range.
     */
    public static function isPrivateOrReserved(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Pick the client IP from an X-Forwarded-For header using rightmost-untrusted:
     * each proxy appends the peer that connected to it, so entries are only
     * trustworthy from the right. The first public address walking right-to-left
     * is the real client; anything left of it is client-supplied and forgeable
     * (a client can pre-seed its own XFF header). When every entry is private
     * (an all-internal chain), fall back to the leftmost valid entry.
     *
     * Lives here, next to the trust model it depends on: it used to be
     * copy-pasted into both request adapters under a "must stay identical"
     * comment, a security derivation maintained twice.
     */
    public static function forwardedClientIp(string $header): ?string
    {
        $entries = array_map('trim', explode(',', $header));

        foreach (array_reverse($entries) as $entry) {
            if (filter_var($entry, FILTER_VALIDATE_IP) !== false && ! self::isPrivateOrReserved($entry)) {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if (filter_var($entry, FILTER_VALIDATE_IP) !== false) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Match a peer address against a single list entry: '*', CIDR, or exact IP.
     */
    private static function matches(string $ip, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_contains($pattern, '/')) {
            return self::cidrMatch($ip, $pattern);
        }

        return $ip === $pattern;
    }

    /**
     * True when the address falls inside the CIDR range. Handles IPv4 and
     * IPv6 via binary comparison; a family mismatch or malformed input
     * never matches.
     */
    private static function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);

        if (! ctype_digit($bits)) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits > strlen($ipBinary) * 8) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        // Compare whole bytes first, then the remaining bits of the last byte.
        $fullBytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainderBits)) & 0xFF;

        return ((ord($ipBinary[$fullBytes]) ^ ord($subnetBinary[$fullBytes])) & $mask) === 0;
    }
}
