<?php

declare(strict_types=1);

namespace Flick\Session;

use Flick\Http\NativeRequest;
use Flick\Http\RequestInterface;

/**
 * Native PHP session-based implementation.
 *
 * This is the default implementation used when no framework adapter is provided.
 * It wraps PHP's native session functions and $_SESSION superglobal to provide
 * a consistent interface for session management.
 *
 * All Flick session data is stored under $_SESSION['flick'] to avoid
 * conflicts with application session data.
 *
 * @example
 * // Standalone usage (default for Flick)
 * $session = new NativeSession();
 * $session->setValue('csrf_token', $token);
 * $value = $session->getValue('csrf_token');
 */
class NativeSession implements SessionInterface
{
    /**
     * The $_SESSION key all Flick data lives under.
     */
    private const NAMESPACE = 'flick';

    protected RequestInterface $request;

    /**
     * Create a new NativeSession instance.
     *
     * @param  RequestInterface|null  $request  Request adapter for secure cookie settings
     * @param  bool  $autoStart  Whether to automatically start the session (default: true for backward compat)
     */
    public function __construct(?RequestInterface $request = null, bool $autoStart = true)
    {
        $this->request = $request ?? new NativeRequest;

        if ($autoStart) {
            $this->start();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => $this->request->isSecure(),
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function regenerateId(bool $deleteOldSession = false): void
    {
        if ($this->isActive()) {
            session_regenerate_id($deleteOldSession);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(string $key, mixed $value): void
    {
        if (! is_array($_SESSION[self::NAMESPACE] ?? null)) {
            // repair a corrupted bag on write - writes dirty the session anyway
            $_SESSION[self::NAMESPACE] = [];
        }

        $_SESSION[self::NAMESPACE][$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(string $key): mixed
    {
        return $this->bag()[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasValue(string $key): bool
    {
        // presence, not truthiness: a stored '0', 0, '' or false is still stored,
        // and Flick takes deliberate care with '0' elsewhere (see inputIsEmpty())
        return array_key_exists($key, $this->bag());
    }

    /**
     * {@inheritdoc}
     */
    public function deleteValue(string $key): void
    {
        if (is_array($_SESSION[self::NAMESPACE] ?? null)) {
            unset($_SESSION[self::NAMESPACE][$key]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        unset($_SESSION[self::NAMESPACE]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->bag();
    }

    /**
     * A read-only view of the Flick bag. One place owns the shape — the six
     * accessors used to restate it with four different guard levels, so a
     * corrupted (non-array) bag meant a TypeError from some and silence from
     * others (audit 2026-08-15, A11). NEVER vivifies $_SESSION[NAMESPACE]:
     * a pure-read request must not dirty the session.
     *
     * @return array<string, mixed>
     */
    private function bag(): array
    {
        $bag = $_SESSION[self::NAMESPACE] ?? [];

        return is_array($bag) ? $bag : [];
    }
}
