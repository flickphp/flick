<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Support\Errors;

/*
 * Audit 2026-08-19, S12 — Errors::add() looked a rule up by the exact string
 * it was handed, and two rule methods (integer(), required()) pass the spec
 * through untouched so a custom message keyed by the alias the developer typed
 * ('r') still fires. Hand one of them a parameter it does not take and the
 * lookup key became 'integer:5', which no message map contains: an undefined
 * index warning, then a TypeError from str_replace() on the null.
 *
 * The bare rule name (the token before the first ':') is now the fallback in
 * both maps. There is deliberately no final '' fallback — a rule name with no
 * message anywhere is a genuine typo and must still fail loudly rather than
 * land a blank error in the bag.
 */

it('falls back to the bare rule name when the spec carries a parameter', function () {
    $rules = require __DIR__.'/../../lang/en/rules.php';
    $errors = new Errors($rules);

    $errors->add('qty', [], 'integer:5', 'qty');

    expect($errors->get('qty'))->toBe(str_replace(':key', 'qty', $rules['integer']));
});

it('lets a custom message keyed by the bare rule name win over the canned text', function () {
    $errors = new Errors(require __DIR__.'/../../lang/en/rules.php');

    $errors->add('qty', ['integer' => 'Whole numbers only for :key'], 'integer:5', 'qty');

    expect($errors->get('qty'))->toBe('Whole numbers only for qty');
});

it('still prefers a custom message keyed by the exact spec the developer typed', function () {
    $errors = new Errors(require __DIR__.'/../../lang/en/rules.php');

    $errors->add('nick', ['r' => 'Pick a nickname'], 'r', 'nick');

    expect($errors->get('nick'))->toBe('Pick a nickname');
});

it('reports a validation error instead of fatalling when request() meets integer:5', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'qty' => 'abc'];

    $form = new Flick(['csrf' => false, 'echo' => false]);
    $form->request('Qty[integer:5]');

    expect($form->ok())->toBeFalse()
        ->and($form->hasError('qty'))->toBeTrue()
        ->and($form->getErrors()['qty'])->toBe('qty must be a number');
});
