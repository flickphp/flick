<?php

declare(strict_types=1);

namespace Flick\Session;

/**
 * Array-based session implementation for testing.
 *
 * This class allows you to test session-dependent code without
 * actual PHP sessions, making tests faster and more isolated.
 *
 * @example
 * // Create a session for testing
 * $session = new ArraySession();
 * $session->setValue('user_id', 123);
 * expect($session->getValue('user_id'))->toBe(123);
 *
 * // Test session regeneration was called
 * $auth->login(1);
 * expect($session->wasRegenerated())->toBeTrue();
 *
 * // Check all stored values
 * $values = $session->getAllValues();
 */
class ArraySession implements SessionInterface
{
    /**
     * Internal storage for session values.
     */
    protected array $values = [];

    /**
     * Whether the session is considered active.
     */
    protected bool $active = true;

    /**
     * The $deleteOldSession flag of every regenerateId() call, in order.
     *
     * The single record of regeneration: wasRegenerated() and
     * getRegenerateCount() derive from it. It used to be a bool AND an int
     * storing the same fact, while the delete flag — the piece Auth always
     * passes true and tests actually want to assert — was discarded by an
     * empty if-branch whose comment claimed it was tracked.
     *
     * @var list<bool>
     */
    protected array $regenerateCalls = [];

    /**
     * Whether destroy() was called.
     */
    protected bool $destroyed = false;

    /**
     * Create a new ArraySession with optional initial values.
     *
     * @param  array  $initialValues  Initial session values to set
     * @param  bool  $active  Whether the session is active (default: true)
     */
    public function __construct(array $initialValues = [], bool $active = true)
    {
        $this->values = $initialValues;
        $this->active = $active;
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->active = true;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerateId(bool $deleteOldSession = false): void
    {
        $this->regenerateCalls[] = $deleteOldSession;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasValue(string $key): bool
    {
        // presence, not truthiness: a stored '0', 0, '' or false is still stored,
        // and Flick takes deliberate care with '0' elsewhere (see inputIsEmpty())
        return array_key_exists($key, $this->values);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteValue(string $key): void
    {
        unset($this->values[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $this->values = [];
        $this->destroyed = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->values;
    }

    // Testing helpers ---------------------------------------------------------

    /**
     * Check if regenerateId() was called at least once.
     *
     * @return bool True if regenerateId() was called
     */
    public function wasRegenerated(): bool
    {
        return $this->regenerateCalls !== [];
    }

    /**
     * Get the number of times regenerateId() was called.
     *
     * @return int Number of regenerateId() calls
     */
    public function getRegenerateCount(): int
    {
        return count($this->regenerateCalls);
    }

    /**
     * The $deleteOldSession flag of each regenerateId() call, in order —
     * so a test can assert not just THAT a login regenerated the session,
     * but that it asked for the old one to be deleted.
     *
     * @return list<bool>
     */
    public function getRegenerateCalls(): array
    {
        return $this->regenerateCalls;
    }

    /**
     * Check if destroy() was called.
     *
     * @return bool True if destroy() was called
     */
    public function wasDestroyed(): bool
    {
        return $this->destroyed;
    }

    /**
     * Get all stored session values.
     *
     * @return array All stored values
     */
    public function getAllValues(): array
    {
        return $this->values;
    }

    /**
     * Set the active state (for testing scenarios).
     *
     * @param  bool  $active  Whether the session should be considered active
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Reset all testing flags.
     */
    public function resetFlags(): static
    {
        $this->regenerateCalls = [];
        $this->destroyed = false;

        return $this;
    }
}
