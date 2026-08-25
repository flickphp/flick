<?php

declare(strict_types=1);

use Flick\Dropdowns\Dropdowns;
use Flick\Flick;
use Flick\Forms\Forms;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;
use Flick\Session\NativeSession;
use Flick\Support\Errors;
use Flick\Support\Support;
use Flick\Views\Views;

/*
|--------------------------------------------------------------------------
| Loader, session and exception hardening
|--------------------------------------------------------------------------
|
| Sections 2.6 to 2.9 of the maintainers' 2026-08-11 pre-release review notes.
|
*/

// §2.6 — a missing file throws instead of exiting the request ----------------
//
// These assert the exception class and that the message names the missing
// path, deliberately not the message markup: the HTML inside exception
// messages is slated for removal (decision 2026-08-11).

it('throws when a view file is missing (§2.6)', function () {
    $views = new Views(
        ['echo' => false],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect(fn () => $views->load('flick/nope.view.php'))
        ->toThrow(RuntimeException::class, 'nope.view.php');
});

it('throws when a custom view asset path is missing (§2.6)', function () {
    $views = new Views(
        ['echo' => false],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect(fn () => $views->loadAsset(__DIR__.'/nope.view.php'))
        ->toThrow(RuntimeException::class, 'nope.view.php');
});

it('throws when a form definition file is missing (§2.6)', function () {
    $forms = new Forms(
        ['form' => ['lang' => 'en']],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect(fn () => $forms->load('nonexistent_form'))
        ->toThrow(RuntimeException::class, 'nonexistent_form');
});

it('throws when a dropdown definition file is missing (§2.6)', function () {
    $dropdowns = new Dropdowns(
        ['form' => ['lang' => 'en']],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect(fn () => $dropdowns->load('nonexistent_dropdown'))
        ->toThrow(RuntimeException::class, 'nonexistent_dropdown');
});

it('propagates a missing dropdown out of string form building (§2.6)', function () {
    // constructing a real Flick registers globalExceptionHandler; save/restore
    $originalHandler = snapshotExceptionHandler();

    $_SERVER['REQUEST_METHOD'] = 'GET';

    try {
        Flick::resetDefaultRequest();
        $form = new Flick(['echo' => false, 'csrf' => false]);

        expect(fn () => $form->create('State|(nonexistent_dropdown)'))
            ->toThrow(RuntimeException::class, 'nonexistent_dropdown');
    } finally {
        unwindExceptionHandlersTo($originalHandler);
    }
});

// §2.6 / §2.7 — loaders reject a traversing name instead of including it -----

it('rejects a traversing dropdown name (§2.7)', function () {
    $dropdowns = new Dropdowns(
        ['form' => ['lang' => 'en']],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect(fn () => $dropdowns->load('../../../../etc/passwd'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a dropdown name containing a directory separator (§2.7)', function () {
    $dropdowns = new Dropdowns(
        ['form' => ['lang' => 'en']],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect(fn () => $dropdowns->load('states/../../secret'))
        ->toThrow(InvalidArgumentException::class);
});

it('still loads a legitimate dropdown name (§2.7)', function () {
    $dropdowns = new Dropdowns(
        ['form' => ['lang' => 'en']],
        new Support(['echo' => false], new Errors, new ArrayRequest),
    );

    expect($dropdowns->load('states'))->toBeArray()->not->toBeEmpty();
});

// §2.8 — a stored falsy value is still a stored value ------------------------

it('reports a stored "0" as present in ArraySession (§2.8)', function () {
    $session = new ArraySession;
    $session->setValue('answer', '0');

    expect($session->hasValue('answer'))->toBeTrue();
});

it('reports a stored empty string as present in ArraySession (§2.8)', function () {
    $session = new ArraySession;
    $session->setValue('answer', '');

    expect($session->hasValue('answer'))->toBeTrue();
});

it('reports a stored false as present in ArraySession (§2.8)', function () {
    $session = new ArraySession;
    $session->setValue('answer', false);

    expect($session->hasValue('answer'))->toBeTrue();
});

it('still reports an unset key as absent in ArraySession (§2.8)', function () {
    expect((new ArraySession)->hasValue('nothing'))->toBeFalse();
});

it('reports a deleted key as absent in ArraySession (§2.8)', function () {
    $session = new ArraySession;
    $session->setValue('answer', '0');
    $session->deleteValue('answer');

    expect($session->hasValue('answer'))->toBeFalse();
});

it('reports a stored "0" as present in NativeSession (§2.8)', function () {
    // autoStart off: session_start() would replace $_SESSION and drop the fixture
    $session = new NativeSession(null, false);
    $session->setValue('answer', '0');

    expect($session->hasValue('answer'))->toBeTrue();

    unset($_SESSION['flick']);
});

it('still reports an unset key as absent in NativeSession (§2.8)', function () {
    $session = new NativeSession(null, false);
    $_SESSION['flick'] = [];

    expect($session->hasValue('nothing'))->toBeFalse();

    unset($_SESSION['flick']);
});

it('reports absence when no Flick session data exists at all (§2.8)', function () {
    $session = new NativeSession(null, false);
    unset($_SESSION['flick']);

    expect($session->hasValue('anything'))->toBeFalse();
});

// §2.9 — escaping moved from message build to render (2026-08-11) ------------
//
// Exception messages are plain text with raw values now; the XSS guarantee
// lives in ExceptionRenderer and is pinned by ExceptionRendererTest. The raw
// plain-text side is pinned by FlickExceptionTest.
