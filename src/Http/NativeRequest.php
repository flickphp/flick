<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Native PHP superglobal-based request implementation.
 *
 * This is the default implementation used when no framework adapter is provided.
 * It wraps PHP's superglobals ($_POST, $_GET, $_SERVER, $_FILES, $_COOKIE, $_ENV)
 * to provide a consistent interface for request data access.
 *
 * @example
 * // Standalone usage (default for Flick)
 * $request = new NativeRequest();
 * $email = $request->post('email');
 * $page = $request->query('page', 1);
 */
class NativeRequest implements RequestInterface
{
    /**
     * @param  array|null  $trustedProxies  Peers whose forwarded headers are honored:
     *                                      IPs, CIDR ranges, or '*'. Null (default) trusts
     *                                      private/loopback peers; [] trusts nobody.
     */
    public function __construct(
        private ?array $trustedProxies = null,
    ) {}

    // POST data ---------------------------------------------------------------

    /**
     * Get a value from POST data.
     *
     * @param  string  $key  The POST key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The POST value or default
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get all POST data.
     *
     * @return array All POST data
     */
    public function postAll(): array
    {
        return $_POST;
    }

    /**
     * Check if a key exists in POST data.
     *
     * @param  string  $key  The key to check
     * @return bool True if key exists
     */
    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $_POST);
    }

    // GET/query data ----------------------------------------------------------

    /**
     * Get a value from query string (GET) data.
     *
     * @param  string  $key  The query key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The query value or default
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get all query string data.
     *
     * @return array All GET data
     */
    public function queryAll(): array
    {
        return $_GET;
    }

    /**
     * Check if a key exists in query string data.
     *
     * @param  string  $key  The key to check
     * @return bool True if key exists
     */
    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $_GET);
    }

    // Combined input (POST priority) ------------------------------------------

    /**
     * Get a value from POST or GET (POST takes priority).
     *
     * @param  string  $key  The key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The value or default
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * Get all input data (POST merged over GET).
     *
     * @return array All input data with POST values taking priority
     */
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Check if a key exists in POST or GET data.
     *
     * @param  string  $key  The key to check
     * @return bool True if key exists in either POST or GET
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $_POST) || array_key_exists($key, $_GET);
    }

    // Files -------------------------------------------------------------------

    /**
     * Get uploaded file data by key.
     *
     * @param  string  $key  The file input name
     * @return array|null File data array or null if not found
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Get all uploaded files.
     *
     * @return array All uploaded files data
     */
    public function files(): array
    {
        return $_FILES;
    }

    /**
     * Check if a file was uploaded with the given key.
     *
     * @param  string  $key  The file input name
     * @return bool True if file exists and was uploaded successfully
     */
    public function hasFile(string $key): bool
    {
        if (! isset($_FILES[$key])) {
            return false;
        }

        return UploadShape::hasUpload($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    // Server/environment ------------------------------------------------------

    /**
     * Get a server/environment variable.
     *
     * @param  string  $key  The server variable key
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The server value or default
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $_SERVER[$key] ?? $default;
    }

    /**
     * Get the HTTP request method.
     *
     * @return string The request method (GET, POST, PUT, DELETE, etc.)
     */
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Check if the request method matches.
     *
     * @param  string  $method  The method to check against
     * @return bool True if methods match (case-insensitive)
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method()) === strtoupper($method);
    }

    /**
     * Check if this is an AJAX/XHR request.
     *
     * @return bool True if X-Requested-With header is XMLHttpRequest
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    // Cookies & headers -------------------------------------------------------

    /**
     * Get a cookie value.
     *
     * @param  string  $key  The cookie name
     * @param  mixed  $default  Default value if cookie doesn't exist
     * @return mixed The cookie value or default
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $_COOKIE[$key] ?? $default;
    }

    /**
     * Check if a cookie exists.
     *
     * @param  string  $key  The cookie name
     * @return bool True if cookie exists
     */
    public function hasCookie(string $key): bool
    {
        return array_key_exists($key, $_COOKIE);
    }

    /**
     * Delete a cookie by setting it to expire in the past.
     *
     * @param  string  $key  The cookie name to delete
     */
    public function deleteCookie(string $key): void
    {
        if (isset($_COOKIE[$key])) {
            setcookie($key, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->isSecure(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            unset($_COOKIE[$key]);
        }
    }

    /**
     * Set a cookie with secure defaults.
     *
     * @param  string  $name  The cookie name
     * @param  string  $value  The cookie value
     * @param  array  $options  Cookie options: expires, path, secure, httponly, samesite
     */
    public function setCookie(string $name, string $value, array $options = []): void
    {
        $defaults = [
            'expires' => 0,
            'path' => '/',
            'secure' => $this->isSecure(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        $options = array_merge($defaults, $options);

        setcookie($name, $value, $options);

        // Update internal cookies array for subsequent reads
        $_COOKIE[$name] = $value;
    }

    /**
     * Get a request header value.
     *
     * Headers are normalized from HTTP_* format in $_SERVER.
     *
     * @param  string  $key  The header name (e.g., 'Content-Type' or 'X-Custom-Header')
     * @param  mixed  $default  Default value if header doesn't exist
     * @return mixed The header value or default
     */
    public function header(string $key, mixed $default = null): mixed
    {
        // Convert header name to $_SERVER format (header names are case-insensitive)
        $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $key));

        // Special cases that don't have the HTTP_ prefix
        if ($serverKey === 'HTTP_CONTENT_TYPE') {
            return $_SERVER['CONTENT_TYPE'] ?? $_SERVER[$serverKey] ?? $default;
        }

        if ($serverKey === 'HTTP_CONTENT_LENGTH') {
            return $_SERVER['CONTENT_LENGTH'] ?? $_SERVER[$serverKey] ?? $default;
        }

        return $_SERVER[$serverKey] ?? $default;
    }

    // Environment -------------------------------------------------------------

    /**
     * Get an environment variable.
     *
     * Checks $_ENV first, then $_SERVER, then getenv() as fallback.
     * This mirrors the common pattern used by frameworks like Laravel.
     *
     * @param  string  $key  The environment variable name
     * @param  mixed  $default  Default value if not found
     * @return mixed The environment value or default
     */
    public function env(string $key, mixed $default = null): mixed
    {
        // Check $_ENV first
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Check $_SERVER (common in some configurations)
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        // Fallback to getenv()
        $value = getenv($key);

        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Get the client's IP address.
     *
     * Handles common proxy headers with fallback to REMOTE_ADDR.
     * Note: For security, only trust proxy headers if you're behind a trusted proxy.
     *
     * @return string The client IP address
     */
    public function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // The X-Forwarded-For header is client-spoofable, so only honor it when
        // the direct peer is a trusted proxy (see ProxyTrust for the model).
        // Client-IP is deliberately NOT consulted: no proxy in a standard
        // chain overwrites it, so it is client-controlled end to end.
        if (ProxyTrust::isTrusted($remote, $this->trustedProxies)) {
            if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $candidate = ProxyTrust::forwardedClientIp($_SERVER['HTTP_X_FORWARDED_FOR']);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return $remote;
    }

    /**
     * Check if the request is over HTTPS.
     *
     * @return bool True if request is secure
     */
    public function isSecure(): bool
    {
        // Standard check
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Check for port 443
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }

        // Check for forwarded protocol, but only from a trusted proxy — the
        // header is client-supplied and forgeable on a direct connection.
        if (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
            && ProxyTrust::isTrusted($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $this->trustedProxies)
        ) {
            return true;
        }

        return false;
    }

    // Utility -----------------------------------------------------------------

    /**
     * Get the request URI.
     *
     * @return string The request URI path
     */
    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Clear the request data (POST and GET).
     *
     * Used after form submission to prevent resubmission on refresh.
     */
    public function clear(): void
    {
        $_POST = [];
        $_GET = [];
    }
}
