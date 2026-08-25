<?php

declare(strict_types=1);

namespace Flick\Session;

/**
 * Session abstraction interface for framework integration.
 *
 * Allows Flick to work seamlessly with Laravel, Symfony, and other frameworks
 * while maintaining zero-config standalone usage with PHP native sessions.
 *
 * All session data is stored under the 'flick' namespace to avoid conflicts
 * with application session data.
 *
 * @example
 * // Standalone usage (default for Flick)
 * $session = new NativeSession();
 * $session->setValue('user_id', 123);
 *
 * // Laravel integration
 * $session = new LaravelSession(session());
 * Flick::setDefaultSession($session);
 */
interface SessionInterface
{
    /**
     * Check if a session is currently active.
     *
     * @return bool True if session is active and ready to use
     */
    public function isActive(): bool;

    /**
     * Start the session if not already started.
     *
     * For native PHP sessions, this calls session_start().
     * For framework adapters, this may be a no-op if the framework
     * handles session lifecycle automatically.
     */
    public function start(): void;

    /**
     * Regenerate the session ID to prevent session fixation attacks.
     *
     * Should be called after authentication state changes (login/logout).
     *
     * @param  bool  $deleteOldSession  Whether to delete the old session data
     */
    public function regenerateId(bool $deleteOldSession = false): void;

    /**
     * Store a value in the session under the Flick namespace.
     *
     * @param  string  $key  The key to store the value under
     * @param  mixed  $value  The value to store
     */
    public function setValue(string $key, mixed $value): void;

    /**
     * Retrieve a value from the session.
     *
     * @param  string  $key  The key to retrieve
     * @return mixed The stored value or null if not found
     */
    public function getValue(string $key): mixed;

    /**
     * Check if a value exists in the session.
     *
     * Presence, not truthiness: a stored '0', 0, '' or false is still a stored
     * value. Adapters must report those as present, or repopulating a field
     * holding '0' breaks.
     *
     * @param  string  $key  The key to check
     * @return bool True if the key has been set, whatever its value
     */
    public function hasValue(string $key): bool;

    /**
     * Remove a value from the session.
     *
     * @param  string  $key  The key to remove
     */
    public function deleteValue(string $key): void;

    /**
     * Destroy all Flick session data.
     *
     * This only removes Flick's namespace data, not the entire session.
     */
    public function destroy(): void;

    /**
     * Get all session data in the Flick namespace.
     *
     * Returns all key-value pairs stored under the Flick namespace.
     * Primarily used for debugging, test mode, and framework integration.
     *
     * @return array All stored session data
     */
    public function getAll(): array;
}
