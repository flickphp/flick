<?php

declare(strict_types=1);

use Flick\Session\NativeSession;

/*
 * Audit 2026-08-15, A11 — NativeSession restated the $_SESSION['flick'] bag
 * shape six times with four different guard levels: a non-array bag (external
 * corruption, a colliding app writing the same key) meant TypeError from
 * hasValue()/getAll(), a fatal from setValue()/deleteValue(), and silent null
 * from getValue(). One private bag() view now owns the shape; every reader
 * treats a corrupted bag as empty, and the write path repairs it.
 *
 * bag() must never vivify the key on read: a pure-read request staying
 * clean is load-bearing (LoaderAndSessionPresenceTest).
 */

beforeEach(function () {
    $this->sessionBackup = $_SESSION ?? null;
    $_SESSION = [];
});

afterEach(function () {
    if ($this->sessionBackup === null) {
        unset($_SESSION);
    } else {
        $_SESSION = $this->sessionBackup;
    }
});

it('treats a corrupted (non-array) bag as empty on every read', function () {
    $_SESSION['flick'] = 'corrupted-by-something-else';
    $session = new NativeSession;

    expect($session->hasValue('anything'))->toBeFalse()
        ->and($session->getValue('anything'))->toBeNull()
        ->and($session->getAll())->toBe([]);
});

it('repairs a corrupted bag on write instead of fataling', function () {
    $_SESSION['flick'] = 'corrupted-by-something-else';
    $session = new NativeSession;

    $session->setValue('name', 'timbo');

    expect($_SESSION['flick'])->toBe(['name' => 'timbo'])
        ->and($session->getValue('name'))->toBe('timbo');
});

it('ignores deleteValue on a corrupted bag instead of fataling', function () {
    $_SESSION['flick'] = 'corrupted-by-something-else';
    $session = new NativeSession;

    $session->deleteValue('anything');

    expect($_SESSION['flick'])->toBe('corrupted-by-something-else');
});

it('never vivifies the bag on a pure read', function () {
    $session = new NativeSession;

    $session->hasValue('x');
    $session->getValue('x');
    $session->getAll();

    expect(array_key_exists('flick', $_SESSION))->toBeFalse();
});
