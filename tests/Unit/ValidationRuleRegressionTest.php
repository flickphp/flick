<?php

declare(strict_types=1);

use Flick\Flick;

beforeEach(function () {
    Flick::resetDefaultValidationDelegate();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $_POST['_id'] = 'myForm';
    $this->form = new Flick([
        'csrf' => false,
    ]);
});

afterEach(function () {
    $_POST = [];
});

// Bug #9 — between must reject non-numeric input like the sibling comparison rules
it('rejects non-numeric input for between (#9)', function () {
    $_POST['age'] = '5cats';
    $this->form->request('age', ['between:1,10'], ['numeric' => 'NOT A NUMBER']);

    expect($this->form->hasError('age'))->toBeTrue();
});

it('still accepts a valid numeric value for between', function () {
    $_POST['age'] = '7';
    $this->form->request('age', ['between:1,10']);

    expect($this->form->hasError('age'))->toBeFalse();
});

it('still rejects an out-of-range numeric value for between', function () {
    $_POST['age'] = '99';
    $this->form->request('age', ['between:1,10']);

    expect($this->form->hasError('age'))->toBeTrue();
});

// Bug #24 — the rule-name prefix strip must not corrupt an argument that
// contains the rule name followed by a colon.
it('does not corrupt an in: argument that contains "in:" (#24)', function () {
    $_POST['host'] = 'domain:example';
    $this->form->request('host', ['in:domain:example,other']);

    expect($this->form->hasError('host'))->toBeFalse();
});

it('still fails in: when the value is not in the list', function () {
    $_POST['host'] = 'nope';
    $this->form->request('host', ['in:domain:example,other']);

    expect($this->form->hasError('host'))->toBeTrue();
});

// Bug #25 — re-validating the same key on one instance must reflect the
// latest result, not keep a stale error.
it('clears a stale error when the same key re-validates as valid (#25)', function () {
    $_POST['pw'] = 'ab';
    $this->form->request('pw', ['min:5']);
    expect($this->form->hasError('pw'))->toBeTrue();

    $_POST['pw'] = 'abcdef';
    $this->form->request('pw', ['min:5']);
    expect($this->form->hasError('pw'))->toBeFalse();
});

// Bug #26 — notRegex must fail closed on a malformed pattern, not silently pass.
it('fails closed when notRegex is given a malformed pattern (#26)', function () {
    $_POST['x'] = 'anything';
    $this->form->request('x', ['notRegex:/[unclosed/']);

    expect($this->form->hasError('x'))->toBeTrue();
});

it('still passes notRegex when the value does not match a valid pattern', function () {
    $_POST['x'] = 'abc';
    $this->form->request('x', ['notRegex:/[0-9]+/']);

    expect($this->form->hasError('x'))->toBeFalse();
});

it('still fails notRegex when the value matches a valid pattern', function () {
    $_POST['x'] = 'abc123';
    $this->form->request('x', ['notRegex:/[0-9]+/']);

    expect($this->form->hasError('x'))->toBeTrue();
});

// Bug #10 — equals must compare strictly; loose != lets numeric look-alikes
// ('1e2', '+100', '100.0') pass an equals:100 check via type juggling.
it('rejects a numeric look-alike for equals (#10)', function (string $lookAlike) {
    $_POST['code'] = $lookAlike;
    $this->form->request('code', ['equals:100']);

    expect($this->form->hasError('code'))->toBeTrue();
})->with(['1e2', '+100', '100.0']);

// '  100' now legitimately trims to '100' before equals runs (TrimInputTest);
// with trim off, the whitespace look-alike must still be rejected — strict
// comparison, no coercion.
it('rejects a whitespace look-alike for equals when trim is off (#10)', function () {
    $form = new Flick([
        'csrf' => false,
        'trim' => false,
    ]);
    $_POST['code'] = '  100';
    $form->request('code', ['equals:100']);

    expect($form->hasError('code'))->toBeTrue();
});

it('rejects "0.0" for equals:0 (#10)', function () {
    $_POST['code'] = '0.0';
    $this->form->request('code', ['equals:0']);

    expect($this->form->hasError('code'))->toBeTrue();
});

it('still accepts an exact string match for equals', function () {
    $_POST['code'] = '100';
    $this->form->request('code', ['equals:100']);

    expect($this->form->hasError('code'))->toBeFalse();
});
