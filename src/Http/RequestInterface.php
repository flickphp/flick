<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Request abstraction interface for framework integration.
 *
 * Allows Flick to work seamlessly with Laravel, Symfony, and other frameworks
 * while maintaining zero-config standalone usage with PHP superglobals.
 */
interface RequestInterface
{
    // POST data ---------------------------------------------------------------

    /**
     * Get a value from POST data.
     */
    public function post(string $key, mixed $default = null): mixed;

    /**
     * Get all POST data.
     */
    public function postAll(): array;

    /**
     * Check if a key exists in POST data.
     */
    public function hasPost(string $key): bool;

    // GET/query data ----------------------------------------------------------

    /**
     * Get a value from query string (GET) data.
     */
    public function query(string $key, mixed $default = null): mixed;

    /**
     * Get all query string data.
     */
    public function queryAll(): array;

    /**
     * Check if a key exists in query string data.
     */
    public function hasQuery(string $key): bool;

    // Combined input (POST priority) ------------------------------------------

    /**
     * Get a value from POST or GET (POST takes priority).
     */
    public function input(string $key, mixed $default = null): mixed;

    /**
     * Get all input data (POST merged over GET).
     */
    public function all(): array;

    /**
     * Check if a key exists in POST or GET data.
     */
    public function has(string $key): bool;

    // Files -------------------------------------------------------------------
    //
    // The shape below is PHP's $_FILES structure, and every implementation must
    // reproduce it - including adapters over frameworks that model uploads as
    // objects. It lived only in the adapters' tests until now, which is why the
    // three implementations had to be brought back into agreement twice (the
    // Laravel adapter's nested hasFile(), the upload service's sparse keys).

    /**
     * Get uploaded file data by key.
     *
     * Returns a $_FILES-style entry with exactly the keys 'name', 'type',
     * 'tmp_name', 'error' and 'size', or null when the key was not submitted.
     *
     * For a single file input each value is a scalar. For an ARRAY input
     * (`files[]`, `files[avatar]`, `files[a][b]`) each of the five values is
     * itself an array carrying the same keys and the same nesting as the input -
     * PHP's parallel-arrays layout, not a list of per-file records. There is no
     * depth limit: whatever nesting the form submits is preserved.
     *
     * Keys are preserved as submitted and are therefore SPARSE: an input posting
     * `files[0]` and `files[2]` yields exactly those two indexes. Never assume a
     * packed list - iterate the keys rather than counting.
     *
     * On a failed upload the entry still exists: 'error' carries the code, while
     * 'tmp_name' and 'type' are '' and 'size' is 0, so a caller cannot mistake a
     * rejected upload for a successful one at a real path.
     */
    public function file(string $key): ?array;

    /**
     * Get all uploaded files.
     *
     * A map of field name => the entry file() would return for that name. Same
     * shape rules; the same sparseness applies within each entry.
     */
    public function files(): array;

    /**
     * Check if a file was uploaded with the given key.
     *
     * True when the entry holds at least one real upload. For an array input
     * that means at least one slot whose error is not UPLOAD_ERR_NO_FILE - a
     * field where every slot is empty is false, and a field carrying a FAILED
     * upload (too large, partial) is true, because something was submitted and
     * the caller needs to report it.
     *
     * @see UploadShape::hasUpload() the one reading of this rule
     */
    public function hasFile(string $key): bool;

    // Server/environment ------------------------------------------------------

    /**
     * Get a server/environment variable.
     */
    public function server(string $key, mixed $default = null): mixed;

    /**
     * Get the HTTP request method.
     */
    public function method(): string;

    /**
     * Check if the request method matches.
     */
    public function isMethod(string $method): bool;

    /**
     * Check if this is an AJAX/XHR request.
     */
    public function isAjax(): bool;

    // Cookies & headers -------------------------------------------------------

    /**
     * Get a cookie value.
     */
    public function cookie(string $key, mixed $default = null): mixed;

    /**
     * Check if a cookie exists.
     */
    public function hasCookie(string $key): bool;

    /**
     * Delete a cookie.
     */
    public function deleteCookie(string $key): void;

    /**
     * Set a cookie.
     *
     * @param  string  $name  The cookie name
     * @param  string  $value  The cookie value
     * @param  array  $options  Cookie options: expires, path, secure, httponly, samesite
     */
    public function setCookie(string $name, string $value, array $options = []): void;

    /**
     * Get a request header value.
     */
    public function header(string $key, mixed $default = null): mixed;

    // Environment -------------------------------------------------------------

    /**
     * Get an environment variable.
     *
     * Checks $_ENV, then $_SERVER, then getenv() as fallback.
     */
    public function env(string $key, mixed $default = null): mixed;

    /**
     * Get the client's IP address.
     *
     * Honors HTTP_X_FORWARDED_FOR (rightmost untrusted entry) only when the
     * direct peer is a trusted proxy (see ProxyTrust), with fallback to
     * REMOTE_ADDR. By default private/loopback peers are trusted; a
     * configured trustedProxies list replaces that heuristic.
     */
    public function ip(): string;

    /**
     * Check if the request is over HTTPS.
     */
    public function isSecure(): bool;

    // Utility -----------------------------------------------------------------

    /**
     * Get the request URI.
     */
    public function uri(): string;

    /**
     * Clear the request data (POST and GET).
     */
    public function clear(): void;
}
