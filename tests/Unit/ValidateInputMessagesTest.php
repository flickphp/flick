<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * Audit 2026-08-16, F3-A — Validate::input()'s $messages parameter advertised
 * a string branch its own body could never execute. Every internal consumer
 * (required(), applyValidationRule(), applyValidationRulesToArray()) is
 * strictly array-typed, so a string either crashed several calls deep once a
 * rule ran, or — with no rules — survived silently as a no-op. The signature
 * is now array-only, so a string fails at the call boundary instead.
 */

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'nickname' => 'timbo'];
    $_GET = [];

    $this->form = new Flick(['csrf' => false, 'echo' => false]);
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

it('rejects a string $messages argument at the call boundary, even with no rules', function () {
    // Before the narrowing this was the silent path: no rules ran, so the
    // string was never touched and the call quietly succeeded.
    $this->form->validate->input('nickname', [], 'Nickname is required');
})->throws(TypeError::class);

it('still accepts array messages and validates normally', function () {
    $value = $this->form->validate->input('nickname', ['required'], []);

    expect($value)->toBe('timbo')
        ->and($this->form->ok())->toBeTrue();
});
