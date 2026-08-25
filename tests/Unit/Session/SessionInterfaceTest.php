<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;
use Flick\Session\NativeSession;
use Flick\Session\SessionInterface;

// Skip native session tests in CI environments
$skipNativeTests = getenv('CI') === 'true' || ! function_exists('session_start');

// ArraySession tests (always run) -----------------------------------------

describe('ArraySession', function () {
    it('implements SessionInterface', function () {
        $session = new ArraySession;

        expect($session)->toBeInstanceOf(SessionInterface::class);
    });

    it('is active by default', function () {
        $session = new ArraySession;

        expect($session->isActive())->toBeTrue();
    });

    it('can be created as inactive', function () {
        $session = new ArraySession([], false);

        expect($session->isActive())->toBeFalse();
    });

    it('can be activated via start()', function () {
        $session = new ArraySession([], false);
        $session->start();

        expect($session->isActive())->toBeTrue();
    });

    it('stores and retrieves values', function () {
        $session = new ArraySession;
        $session->setValue('key', 'value');

        expect($session->getValue('key'))->toBe('value');
    });

    it('returns null for non-existent keys', function () {
        $session = new ArraySession;

        expect($session->getValue('nonexistent'))->toBeNull();
    });

    it('checks if values exist', function () {
        $session = new ArraySession;
        $session->setValue('exists', 'value');
        $session->setValue('empty', '');
        $session->setValue('zero', 0);

        expect($session->hasValue('exists'))->toBeTrue();
        expect($session->hasValue('nonexistent'))->toBeFalse();
        // hasValue() reports presence, not truthiness: a stored '' or 0 was still
        // stored, and reporting it absent broke repopulating a field holding '0'
        expect($session->hasValue('empty'))->toBeTrue();
        expect($session->hasValue('zero'))->toBeTrue();
    });

    it('deletes values', function () {
        $session = new ArraySession;
        $session->setValue('key', 'value');
        $session->deleteValue('key');

        expect($session->getValue('key'))->toBeNull();
        expect($session->hasValue('key'))->toBeFalse();
    });

    it('destroys all values', function () {
        $session = new ArraySession;
        $session->setValue('key1', 'value1');
        $session->setValue('key2', 'value2');
        $session->destroy();

        expect($session->getAllValues())->toBe([]);
        expect($session->wasDestroyed())->toBeTrue();
    });

    it('tracks regenerateId calls', function () {
        $session = new ArraySession;

        expect($session->wasRegenerated())->toBeFalse();
        expect($session->getRegenerateCount())->toBe(0);

        $session->regenerateId();
        expect($session->wasRegenerated())->toBeTrue();
        expect($session->getRegenerateCount())->toBe(1);

        $session->regenerateId();
        expect($session->getRegenerateCount())->toBe(2);
    });

    // Audit 2026-08-15, A10: one fact was stored twice ($regenerated bool +
    // $regenerateCount int) while the security-relevant piece — whether the
    // caller asked for the OLD session to be deleted, which Auth always does —
    // was read into an empty if-branch whose comment claimed it was "tracked"
    // and then discarded. The call list is now the single record, and the
    // delete flag is finally assertable.
    it('records the deleteOldSession flag of every regenerateId call', function () {
        $session = new ArraySession;

        $session->regenerateId(true);
        $session->regenerateId();

        expect($session->getRegenerateCalls())->toBe([true, false]);

        $session->resetFlags();
        expect($session->getRegenerateCalls())->toBe([]);
    });

    it('can reset testing flags', function () {
        $session = new ArraySession;
        $session->regenerateId();
        $session->destroy();
        $session->resetFlags();

        expect($session->wasRegenerated())->toBeFalse();
        expect($session->getRegenerateCount())->toBe(0);
        expect($session->wasDestroyed())->toBeFalse();
    });

    it('accepts initial values', function () {
        $session = new ArraySession(['user_id' => 123, 'role' => 'admin']);

        expect($session->getValue('user_id'))->toBe(123);
        expect($session->getValue('role'))->toBe('admin');
    });

    it('returns all stored values via getAllValues helper', function () {
        $session = new ArraySession;
        $session->setValue('a', 1);
        $session->setValue('b', 2);

        expect($session->getAllValues())->toBe(['a' => 1, 'b' => 2]);
    });

    it('returns all stored values via getAll interface method', function () {
        $session = new ArraySession;
        $session->setValue('foo', 'bar');
        $session->setValue('baz', 123);

        expect($session->getAll())->toBe(['foo' => 'bar', 'baz' => 123]);
    });

    it('returns empty array when no values stored via getAll', function () {
        $session = new ArraySession;

        expect($session->getAll())->toBe([]);
    });

    it('can set active state for testing', function () {
        $session = new ArraySession;
        $session->setActive(false);

        expect($session->isActive())->toBeFalse();
    });
});

// NativeSession tests -----------------------------------------------------

describe('NativeSession', function () use ($skipNativeTests) {
    it('implements SessionInterface', function () use ($skipNativeTests) {
        if ($skipNativeTests) {
            $this->markTestSkipped('Skipping native session test in CI');
        }

        $session = new NativeSession(new ArrayRequest, false);
        expect($session)->toBeInstanceOf(SessionInterface::class);
    });

    it('can be created with autoStart disabled', function () use ($skipNativeTests) {
        if ($skipNativeTests) {
            $this->markTestSkipped('Skipping native session test in CI');
        }

        // Create without auto-start
        $session = new NativeSession(new ArrayRequest, false);

        // Session should not have been started yet
        // (though it may be active from a previous test)
        expect($session)->toBeInstanceOf(SessionInterface::class);
    });
});

// Flick session resolution tests ------------------------------------------

describe('Flick session resolution', function () {
    beforeEach(function () {
        Flick::resetDefaultSession();
        Flick::resetDefaultRequest();
    });

    afterEach(function () {
        Flick::resetDefaultSession();
        Flick::resetDefaultRequest();
    });

    it('uses ArraySession when passed in config', function () {
        $session = new ArraySession;
        $request = ArrayRequest::createGet();

        $form = new Flick([
            'session' => $session,
            'request' => $request,
            'csrf' => false,
        ]);

        expect($form->session)->toBe($session);
    });

    it('uses static default session when set', function () {
        $session = new ArraySession;
        $request = ArrayRequest::createGet();

        Flick::setDefaultSession($session);

        $form = new Flick([
            'request' => $request,
            'csrf' => false,
        ]);

        expect($form->session)->toBe($session);
    });

    it('prefers config session over static default', function () {
        $configSession = new ArraySession(['source' => 'config']);
        $staticSession = new ArraySession(['source' => 'static']);
        $request = ArrayRequest::createGet();

        Flick::setDefaultSession($staticSession);

        $form = new Flick([
            'session' => $configSession,
            'request' => $request,
            'csrf' => false,
        ]);

        expect($form->session)->toBe($configSession);
    });

    it('makes session available via Support', function () {
        $session = new ArraySession;
        $request = ArrayRequest::createGet();

        $form = new Flick([
            'session' => $session,
            'request' => $request,
            'csrf' => false,
        ]);

        expect($form->support->session())->toBe($session);
    });
});
