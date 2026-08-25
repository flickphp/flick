<?php

declare(strict_types=1);

use Flick\Flick;

/*
|--------------------------------------------------------------------------
| requiredWith decides emptiness the same way required() does
|--------------------------------------------------------------------------
|
| required() trims before its emptiness check, deliberately, so "   " fails it
| and matches the client-side rule. requiredWith() did not, on either of the two
| values it looks at, so under 'trim' => false the two runtimes disagreed in
| BOTH directions:
|
|   input '   ', match filled  -> PHP accepted,  JS rejected
|   input '',    match '   '   -> PHP errored,   JS accepted
|
| The second one is the worse of the two: the browser lets the submit through
| and the server rejects it, so the visitor gets an error they were never
| warned about.
|
| Under the default trim policy the pipeline strips the whitespace before any
| rule runs, so neither was reachable - which is why this sat unnoticed.
|
| The trim happens inside the rule. applyTrimPolicy() is untouched: 'trim' =>
| false still means the rule receives, and modifiers still see, the raw value.
|
*/

beforeEach(function () {
    Flick::resetDefaultValidationDelegate();
    $_SERVER['REQUEST_METHOD'] = 'POST';
});

afterEach(function () {
    $_POST = [];
});

/** True when the value is ACCEPTED (no error on the field). */
function requiredWithAccepts(string $input, string $match, bool $trim): bool
{
    $_POST = ['_id' => 'myForm', 'field' => $input, 'other' => $match];

    $form = new Flick(['csrf' => false, 'echo' => false, 'trim' => $trim]);
    $form->request('field', ['requiredWith:other']);

    return ! $form->hasError('field');
}

/** The client rule, transcribed from RuleDefinitions::requiredWith(). */
function requiredWithJsAccepts(string $input, string $match): bool
{
    return trim($match) === '' || trim($input) !== '';
}

it('agrees with the client rule with trim off', function (string $input, string $match) {
    expect(requiredWithAccepts($input, $match, trim: false))
        ->toBe(requiredWithJsAccepts($input, $match));
})->with([
    'both filled' => ['x', 'y'],
    'input empty, match filled' => ['', 'y'],
    'input whitespace, match filled' => ['   ', 'y'],
    'both empty' => ['', ''],
    'input empty, match whitespace' => ['', '   '],
    'both whitespace' => ['   ', '   '],
]);

it('agrees with the client rule with trim on', function (string $input, string $match) {
    expect(requiredWithAccepts($input, $match, trim: true))
        ->toBe(requiredWithJsAccepts($input, $match));
})->with([
    'both filled' => ['x', 'y'],
    'input empty, match filled' => ['', 'y'],
    'input whitespace, match filled' => ['   ', 'y'],
    'both empty' => ['', ''],
    'input empty, match whitespace' => ['', '   '],
    'both whitespace' => ['   ', '   '],
]);

it('treats a whitespace-only value as missing, like required does', function () {
    // The two rules answer the same question, so they must answer it the same way
    $_POST = ['_id' => 'myForm', 'field' => '   ', 'other' => 'filled'];

    $form = new Flick(['csrf' => false, 'echo' => false, 'trim' => false]);
    $form->request('field', ['requiredWith:other']);
    $form->request('solo', ['required']);

    expect($form->hasError('field'))->toBeTrue()
        ->and($form->hasError('solo'))->toBeTrue();
});

it('does not require a value when the match field is only whitespace', function () {
    $_POST = ['_id' => 'myForm', 'field' => '', 'other' => '   '];

    $form = new Flick(['csrf' => false, 'echo' => false, 'trim' => false]);
    $form->request('field', ['requiredWith:other']);

    expect($form->hasError('field'))->toBeFalse();
});

it('still handles an array match field without erroring', function () {
    // applyTrimPolicy() returns mixed - a checkbox group arrives as an array,
    // and trimming the match value must not assume a string.
    $_POST = ['_id' => 'myForm', 'field' => '', 'colors' => ['red', 'blue']];

    $form = new Flick(['csrf' => false, 'echo' => false, 'trim' => false]);
    $form->request('field', ['requiredWith:colors']);

    // a non-empty array counts as present, so the field is required
    expect($form->hasError('field'))->toBeTrue();
});

it('treats an empty array match field as absent', function () {
    $_POST = ['_id' => 'myForm', 'field' => '', 'colors' => []];

    $form = new Flick(['csrf' => false, 'echo' => false, 'trim' => false]);
    $form->request('field', ['requiredWith:colors']);

    expect($form->hasError('field'))->toBeFalse();
});
