<?php

declare(strict_types=1);

use Flick\Support\Errors;
use Flick\Support\Support;

/*
 * Decided 2026-08-20 (Tim): Pro's end-user messages translate through the
 * same lang/<code>/messages.php core loads - one mechanism, not two. Every
 * service holds a Support built from the merged config (Flick constructs it
 * after setApplicationLanguage() has run), so Support::message() is the seam:
 * it reads config['applicationMessages'], the shipped English with the
 * selected translation laid over it key by key, and substitutes the :name
 * placeholders rules.php already uses.
 *
 * There is deliberately no English default at the call site. The shipped
 * file is the single source of truth; a key it lacks is a Flick bug, which
 * MessageKeysContractTest catches and message() refuses loudly.
 */

it('returns the text the merged language map holds for a key', function () {
    $english = require __DIR__.'/../../lang/en/messages.php';
    $support = new Support([
        'applicationMessages' => array_replace($english, [
            'SessionHasExpired' => 'Tu sesión ha expirado.',
        ]),
    ], new Errors);

    expect($support->message('SessionHasExpired'))->toBe('Tu sesión ha expirado.')
        ->and($support->message('MessagesHeader'))->toBe($english['MessagesHeader']);
});

it('substitutes :placeholders from the replacements array', function () {
    $support = new Support([
        'applicationMessages' => ['Greeting' => 'Hello :name, you have :count new messages.'],
    ], new Errors);

    expect($support->message('Greeting', ['name' => 'Ada', 'count' => 3]))
        ->toBe('Hello Ada, you have 3 new messages.');
});

it('reads the shipped English file when built without a language map', function () {
    // Flick always passes the merged map. A Support built by hand - a test,
    // or a developer exercising their own service - gets the shipped English,
    // the same text Flick itself uses when no `lang` is configured.
    $english = require __DIR__.'/../../lang/en/messages.php';
    $support = new Support([], new Errors);

    expect($support->message('MessagesHeader'))->toBe($english['MessagesHeader']);
});

it('throws a LogicException naming a key the map does not hold', function () {
    $support = new Support(['applicationMessages' => ['Known' => 'x']], new Errors);

    $support->message('NoSuchKey');
})->throws(LogicException::class, 'NoSuchKey');
