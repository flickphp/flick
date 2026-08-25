<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Exception-handler stack hygiene helpers
|--------------------------------------------------------------------------
|
| PHP keeps exception handlers on a stack: set_exception_handler() always
| pushes, restore_exception_handler() pops. The suite constructs standalone
| Flick instances everywhere, and each construction pushes a global handler,
| so tests need to read the active handler without pushing (snapshot) and to
| pop everything a test pushed (unwind). These tests pin the two helpers in
| tests/Pest.php that every beforeEach/afterEach relies on.
|
*/

it('reads the active handler without changing the stack', function () {
    $sentinel = fn (Throwable $e) => null;
    set_exception_handler($sentinel);

    expect(snapshotExceptionHandler())->toBe($sentinel)
        ->and(snapshotExceptionHandler())->toBe($sentinel);

    // if snapshot leaked a stack entry, this pop would remove the leak
    // instead of the sentinel and the sentinel would still be active
    restore_exception_handler();
    expect(snapshotExceptionHandler())->not->toBe($sentinel);
});

it('unwinds pushed handlers back to a target', function () {
    $base = snapshotExceptionHandler();

    set_exception_handler(fn (Throwable $e) => null);
    set_exception_handler(fn (Throwable $e) => null);
    set_exception_handler(fn (Throwable $e) => null);

    unwindExceptionHandlersTo($base);

    expect(snapshotExceptionHandler())->toBe($base);
});

it('leaves the stack alone when already at the target', function () {
    $sentinel = fn (Throwable $e) => null;
    set_exception_handler($sentinel);

    unwindExceptionHandlersTo($sentinel);

    expect(snapshotExceptionHandler())->toBe($sentinel);

    restore_exception_handler();
});
