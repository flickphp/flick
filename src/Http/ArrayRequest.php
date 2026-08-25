<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Array-based request implementation for testing.
 *
 * This class allows you to create request objects with predetermined data,
 * making it easy to test form handling without manipulating superglobals.
 *
 * @example
 * // Create a POST request for testing
 * $request = ArrayRequest::createPost(['email' => 'test@example.com', 'name' => 'John']);
 *
 * // Create a GET request
 * $request = ArrayRequest::createGet(['page' => '2', 'sort' => 'name']);
 *
 * // Create an AJAX request
 * $request = ArrayRequest::createAjax(['email' => 'test@example.com']);
 *
 * // Use fluent setters for complex scenarios
 * $request = ArrayRequest::createPost(['email' => 'test@example.com'])
 *     ->setFile('avatar', ['name' => 'photo.jpg', 'tmp_name' => '/tmp/abc', 'size' => 1024, 'error' => 0])
 *     ->setCookie('session', 'abc123');
 */
class ArrayRequest implements RequestInterface
{
    protected array $post = [];

    protected array $query = [];

    protected array $server = [];

    protected array $files = [];

    protected array $cookies = [];

    protected array $env = [];

    protected array $deletedCookies = [];

    protected array $setCookies = [];

    protected ?array $trustedProxies = null;

    /**
     * Create a new ArrayRequest with optional initial data.
     */
    public function __construct(array $data = [])
    {
        $this->post = $data['post'] ?? [];
        $this->query = $data['query'] ?? $data['get'] ?? [];
        $this->server = array_merge($this->getDefaultServer(), $data['server'] ?? []);
        $this->files = $data['files'] ?? [];
        $this->cookies = $data['cookies'] ?? [];
        $this->env = $data['env'] ?? [];
        $this->trustedProxies = $data['trustedProxies'] ?? null;
    }

    /**
     * Get default server values.
     */
    protected function getDefaultServer(): array
    {
        return [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
    }

    // Static factory methods --------------------------------------------------

    /**
     * Create a GET request.
     *
     * @param  array  $query  Query string parameters
     */
    public static function createGet(array $query = []): static
    {
        return new static([
            'query' => $query,
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);
    }

    /**
     * Create a POST request.
     *
     * @param  array  $post  POST data
     * @param  array  $query  Optional query string parameters
     */
    public static function createPost(array $post = [], array $query = []): static
    {
        return new static([
            'post' => $post,
            'query' => $query,
            'server' => ['REQUEST_METHOD' => 'POST'],
        ]);
    }

    /**
     * Create an AJAX/XHR POST request.
     *
     * @param  array  $post  POST data
     */
    public static function createAjax(array $post = []): static
    {
        return new static([
            'post' => $post,
            'server' => [
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        ]);
    }

    /**
     * Create a multipart form POST request with file upload.
     *
     * @param  array  $post  POST data
     * @param  array  $files  Files data
     */
    public static function createMultipart(array $post = [], array $files = []): static
    {
        return new static([
            'post' => $post,
            'files' => $files,
            'server' => [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'multipart/form-data',
            ],
        ]);
    }

    // Fluent setters ----------------------------------------------------------

    /**
     * Set POST data.
     *
     * @param  array  $data  POST data to set
     */
    public function setPost(array $data): static
    {
        $this->post = $data;

        return $this;
    }

    /**
     * Add a single POST value.
     *
     * @param  string  $key  The key
     * @param  mixed  $value  The value
     */
    public function addPost(string $key, mixed $value): static
    {
        $this->post[$key] = $value;

        return $this;
    }

    /**
     * Set query/GET data.
     *
     * @param  array  $data  Query data to set
     */
    public function setQuery(array $data): static
    {
        $this->query = $data;

        return $this;
    }

    /**
     * Add a single query value.
     *
     * @param  string  $key  The key
     * @param  mixed  $value  The value
     */
    public function addQuery(string $key, mixed $value): static
    {
        $this->query[$key] = $value;

        return $this;
    }

    /**
     * Set server data.
     *
     * @param  array  $data  Server data to merge with defaults
     */
    public function setServer(array $data): static
    {
        $this->server = array_merge($this->server, $data);

        return $this;
    }

    /**
     * Set a file upload.
     *
     * @param  string  $key  The file input name
     * @param  array  $file  File data (name, tmp_name, size, error, type)
     */
    public function setFile(string $key, array $file): static
    {
        $this->files[$key] = array_merge([
            'name' => 'file.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/'.uniqid(),
            'error' => UPLOAD_ERR_OK,
            'size' => 0,
        ], $file);

        return $this;
    }

    /**
     * Set all files.
     *
     * @param  array  $files  Files data
     */
    public function setFiles(array $files): static
    {
        $this->files = $files;

        return $this;
    }

    /**
     * Set a cookie value directly (fluent setter for test setup).
     *
     * Use this for setting up test fixtures. For testing the actual
     * cookie-setting behavior, use the interface method setCookie()
     * which also records metadata.
     *
     * @param  string  $key  Cookie name
     * @param  mixed  $value  Cookie value
     */
    public function withCookie(string $key, mixed $value): static
    {
        $this->cookies[$key] = $value;

        return $this;
    }

    /**
     * Set all cookies.
     *
     * @param  array  $cookies  Cookies data
     */
    public function setCookies(array $cookies): static
    {
        $this->cookies = $cookies;

        return $this;
    }

    /**
     * Set an environment variable.
     *
     * @param  string  $key  Environment variable name
     * @param  mixed  $value  Value
     */
    public function setEnv(string $key, mixed $value): static
    {
        $this->env[$key] = $value;

        return $this;
    }

    /**
     * Set the request method.
     *
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function setMethod(string $method): static
    {
        $this->server['REQUEST_METHOD'] = strtoupper($method);

        return $this;
    }

    /**
     * Set the request URI.
     *
     * @param  string  $uri  Request URI
     */
    public function setUri(string $uri): static
    {
        $this->server['REQUEST_URI'] = $uri;

        return $this;
    }

    /**
     * Mark this as an AJAX request.
     */
    public function asAjax(): static
    {
        $this->server['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        return $this;
    }

    /**
     * Mark this as a secure (HTTPS) request.
     */
    public function asSecure(): static
    {
        $this->server['HTTPS'] = 'on';
        $this->server['SERVER_PORT'] = '443';

        return $this;
    }

    /**
     * Set the client IP address.
     *
     * @param  string  $ip  IP address
     */
    public function setIp(string $ip): static
    {
        $this->server['REMOTE_ADDR'] = $ip;

        return $this;
    }

    // POST data ---------------------------------------------------------------

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function postAll(): array
    {
        return $this->post;
    }

    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    // GET/query data ----------------------------------------------------------

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function queryAll(): array
    {
        return $this->query;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    // Combined input (POST priority) ------------------------------------------

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->query);
    }

    // Files -------------------------------------------------------------------

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function hasFile(string $key): bool
    {
        if (! isset($this->files[$key])) {
            return false;
        }

        return UploadShape::hasUpload($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    // Server/environment ------------------------------------------------------

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->method()) === strtoupper($method);
    }

    public function isAjax(): bool
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH'])
            && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    // Cookies & headers -------------------------------------------------------

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function hasCookie(string $key): bool
    {
        return array_key_exists($key, $this->cookies);
    }

    public function deleteCookie(string $key): void
    {
        unset($this->cookies[$key]);
        $this->deletedCookies[] = $key;
    }

    /**
     * Set a cookie (stores in memory for testing).
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

        // Store the set cookie for testing inspection
        $this->setCookies[$name] = [
            'value' => $value,
            'options' => $options,
        ];

        // Update internal cookies array for subsequent reads
        $this->cookies[$name] = $value;
    }

    /**
     * Check if a cookie was deleted (for testing).
     *
     * @param  string  $key  Cookie name
     * @return bool True if cookie was deleted
     */
    public function wasCookieDeleted(string $key): bool
    {
        return in_array($key, $this->deletedCookies);
    }

    /**
     * Get all cookies that were set via setCookie() (for testing).
     *
     * @return array Array of cookie name => ['value' => ..., 'options' => ...]
     */
    public function getSetCookies(): array
    {
        return $this->setCookies;
    }

    /**
     * Check if a specific cookie was set via setCookie() (for testing).
     *
     * @param  string  $name  Cookie name
     * @return bool True if cookie was set
     */
    public function wasCookieSet(string $name): bool
    {
        return isset($this->setCookies[$name]);
    }

    /**
     * Get the data for a specific cookie that was set (for testing).
     *
     * @param  string  $name  Cookie name
     * @return array|null Cookie data or null if not set
     */
    public function getSetCookie(string $name): ?array
    {
        return $this->setCookies[$name] ?? null;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        // Convert header name to $_SERVER format (header names are case-insensitive)
        $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $key));

        // Special cases that don't have the HTTP_ prefix
        if ($serverKey === 'HTTP_CONTENT_TYPE') {
            return $this->server['CONTENT_TYPE'] ?? $this->server[$serverKey] ?? $default;
        }

        if ($serverKey === 'HTTP_CONTENT_LENGTH') {
            return $this->server['CONTENT_LENGTH'] ?? $this->server[$serverKey] ?? $default;
        }

        return $this->server[$serverKey] ?? $default;
    }

    // Environment -------------------------------------------------------------

    public function env(string $key, mixed $default = null): mixed
    {
        // Check internal env array first
        if (isset($this->env[$key])) {
            return $this->env[$key];
        }

        // Check server array (some env vars end up here)
        if (isset($this->server[$key])) {
            return $this->server[$key];
        }

        return $default;
    }

    public function ip(): string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? '127.0.0.1';

        // Only honor the spoofable X-Forwarded-For header when the direct peer
        // is a trusted proxy (see ProxyTrust for the model). Client-IP is
        // deliberately NOT consulted: it is client-controlled end to end.
        if (ProxyTrust::isTrusted($remote, $this->trustedProxies)) {
            if (! empty($this->server['HTTP_X_FORWARDED_FOR'])) {
                $candidate = ProxyTrust::forwardedClientIp($this->server['HTTP_X_FORWARDED_FOR']);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return $remote;
    }

    public function isSecure(): bool
    {
        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($this->server['SERVER_PORT']) && $this->server['SERVER_PORT'] == 443) {
            return true;
        }

        if (
            isset($this->server['HTTP_X_FORWARDED_PROTO'])
            && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https'
            && ProxyTrust::isTrusted($this->server['REMOTE_ADDR'] ?? '127.0.0.1', $this->trustedProxies)
        ) {
            return true;
        }

        return false;
    }

    // Utility -----------------------------------------------------------------

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function clear(): void
    {
        $this->post = [];
        $this->query = [];
    }
}
