<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;

/*
 * Audit 2026-08-15, A17 — whether to install Flick's global exception handler
 * was inferred from an unrelated static (`self::$defaultRequest === null`)
 * instead of from how THIS instance's request resolved. resolveRequest() had
 * already classified the caller into three tiers and discarded the answer, so
 * `new Flick(['request' => $adapter])` — the documented "caller supplied their
 * own HTTP layer" tier — still installed Flick's handler, which renders
 * Flick's 500 page and exits, swallowing the host's own error page.
 * The gate now follows the resolution: whoever supplies the HTTP layer owns
 * error rendering.
 */

it('does not install the global handler when the caller supplies a request adapter', function () {
    $before = snapshotExceptionHandler();

    new Flick(['csrf' => false, 'echo' => false, 'request' => new ArrayRequest]);

    expect(snapshotExceptionHandler())->toBe($before);
});

it('does not install the global handler when a framework default request is set', function () {
    Flick::setDefaultRequest(new ArrayRequest);

    try {
        $before = snapshotExceptionHandler();

        new Flick(['csrf' => false, 'echo' => false]);

        expect(snapshotExceptionHandler())->toBe($before);
    } finally {
        Flick::resetDefaultRequest();
    }
});

it('still installs the global handler for a bare standalone construction', function () {
    $before = snapshotExceptionHandler();

    new Flick(['csrf' => false, 'echo' => false]);

    expect(snapshotExceptionHandler())->not->toBe($before);

    unwindExceptionHandlersTo($before);
});
