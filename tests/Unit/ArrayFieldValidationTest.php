<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * Bug #3 — an array-shaped value must never skip validation wholesale.
 *
 * A scalar field receiving an array (attacker rewrites email= to email[]=)
 * must FAIL validation. A genuinely multi-value field — named with [] the way
 * it renders — has its rules applied to every submitted element.
 */

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm'];
    $_GET = [];

    $this->form = new Flick(['csrf' => false, 'echo' => false]);
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

// --- scalar field receiving an array ---------------------------------------

it('fails validation when a scalar field receives an array (#3)', function () {
    $_POST['email'] = ['not-an-email'];

    $value = $this->form->request('email', 'required,email');

    expect($this->form->ok())->toBeFalse()
        ->and($this->form->hasError('email'))->toBeTrue()
        ->and($value)->toBe('');
});

it('fails validation when a scalar field receives a nested array (#3)', function () {
    $_POST['email'] = [['deep']];

    $value = $this->form->request('email', 'required,email');

    expect($this->form->hasError('email'))->toBeTrue()
        ->and($value)->toBe('');
});

it('does not return the raw array to the caller for a scalar field (#3)', function () {
    $_POST['password'] = ['x'];

    $value = $this->form->request('password', 'required,min:8');

    expect($value)->not->toBeArray();
});

it('still validates a scalar value on a scalar field', function () {
    $_POST['email'] = 'valid@example.com';

    $this->form->request('email', 'required,email');

    expect($this->form->ok())->toBeTrue();
});

// --- no rules: array passes through unchanged -------------------------------

it('still returns an array untouched when no rules are given', function () {
    $_POST['colors'] = ['red', 'green'];

    $value = $this->form->request('colors');

    expect($value)->toBe(['red', 'green'])
        ->and($this->form->ok())->toBeTrue();
});

// --- multi-value fields (name written with [] as it renders) ----------------

it('applies in: to every element of a multi-value field (#3)', function () {
    $_POST['colors'] = ['red', 'purple'];

    $this->form->request('colors[]', 'in:red,green,blue');

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('passes in: when every element is allowed (#3)', function () {
    $_POST['colors'] = ['red', 'blue'];

    $value = $this->form->request('colors[]', 'in:red,green,blue');

    expect($this->form->ok())->toBeTrue()
        ->and($value)->toBe(['red', 'blue']);
});

it('applies notIn: to every element of a multi-value field (#3)', function () {
    $_POST['roles'] = ['admin'];

    $this->form->request('roles[]', 'notIn:admin,root');

    expect($this->form->hasError('roles'))->toBeTrue();
});

it('fails required for an array holding only an empty string (#3)', function () {
    $_POST['colors'] = [''];

    $this->form->request('colors[]', 'required');

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('fails required when a multi-value field is absent entirely (#3)', function () {
    $this->form->request('colors[]', 'required');

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('passes required when a multi-value field has a checked value (#3)', function () {
    $_POST['colors'] = ['red'];

    $value = $this->form->request('colors[]', 'required');

    expect($this->form->ok())->toBeTrue()
        ->and($value)->toBe(['red']);
});

it('fires a custom message keyed r for the required rule on an empty multi-value field', function () {
    $_POST['colors'] = [];

    $this->form->request('colors[]', ['r'], ['r' => 'Pick at least one color']);

    expect($this->form->hasError('colors'))
        ->toBeTrue()
        ->and($this->form->getError('colors'))
        ->toBe('Pick at least one color');
});

it('runs accepted on an empty multi-value submission, matching the absent case', function () {
    $_POST['terms'] = [];

    $this->form->request('terms[]', 'accepted');

    expect($this->form->hasError('terms'))->toBeTrue();
});

it('runs requiredWith on an empty multi-value submission', function () {
    $_POST['colors'] = [];
    $_POST['palette'] = 'warm';

    $this->form->request('colors[]', 'requiredWith:palette');

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('still leaves an empty multi-value field alone when no rule runs on empty', function () {
    $_POST['colors'] = [];

    $this->form->request('colors[]', 'in:red,green,blue');

    expect($this->form->ok())->toBeTrue();
});

it('still fails required on an empty multi-value submission via the r alias', function () {
    $_POST['colors'] = [];

    $this->form->request('colors[]', 'r');

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('fails cleanly when a multi-value element is itself an array (#3)', function () {
    $_POST['colors'] = [['nested']];

    $this->form->request('colors[]', 'required');

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('applies value rules per element of a multi-value field (#3)', function () {
    $_POST['nums'] = ['1', 'x'];

    $this->form->request('nums[]', 'integer');

    expect($this->form->hasError('nums'))->toBeTrue();
});

// --- definition-string flow (createAndValidate / request with a form string) -

it('applies required to a checkbox group defined in a form string (#3)', function () {
    $this->form->request('Colors|checkbox([red:Red, green:Green])[required]');

    expect($this->form->hasError('colors'))->toBeTrue()
        ->and($this->form->getError('colors'))->toContain('required');
});

it('accepts a checked checkbox group defined in a form string (#3)', function () {
    $_POST['colors'] = ['red'];

    $data = $this->form->request('Name, Colors|checkbox([red:Red, green:Green])[required]');

    expect($this->form->ok())->toBeTrue()
        ->and($data['colors'])->toBe(['red']);
});

it('does not misread checkbox group options as validation rules (#3)', function () {
    $_POST['name'] = 'Tim';

    $this->form->request('Name, Colors|checkbox([red:Red, green:Green])');

    expect($this->form->ok())->toBeTrue();
});

it('applies in: per element for a selectMultiple defined in a form string (#3)', function () {
    $_POST['skills'] = ['php', 'hax'];

    $this->form->request('Skills|selectMultiple([php:PHP, js:JS])[required, in:php,js]');

    expect($this->form->hasError('skills'))->toBeTrue();
});

it('keeps a regex rule containing parentheses intact in a form string', function () {
    $_POST['code'] = 'abc';

    $this->form->request('code[regex:/^(x|y)$/]');

    expect($this->form->hasError('code'))->toBeTrue();

    $_POST['code'] = 'x';
    $form2 = new Flick(['csrf' => false, 'echo' => false]);
    $form2->request('code[regex:/^(x|y)$/]');

    expect($form2->ok())->toBeTrue();
});

// --- fastPost (array definition) flow ---------------------------------------

it('applies in: per element for a selectMultiple array definition (#3)', function () {
    $_POST['skills'] = ['php', 'hax'];

    $def = ['fields' => [
        'skills' => [
            'type' => 'selectMultiple',
            'label' => 'Skills',
            'name' => 'skills',
            'options' => ['php' => 'PHP', 'js' => 'JS'],
            'rules' => ['required', 'in:php,js'],
        ],
    ]];

    $this->form->request($def);

    expect($this->form->hasError('skills'))->toBeTrue();
});

it('accepts valid elements for a checkbox-with-options array definition (#3)', function () {
    $_POST['colors'] = ['red'];

    $def = ['fields' => [
        'colors' => [
            'type' => 'checkbox',
            'label' => 'Colors',
            'name' => 'colors',
            'options' => ['red' => 'Red', 'green' => 'Green'],
            'rules' => ['required', 'in:red,green'],
        ],
    ]];

    $this->form->request($def);

    expect($this->form->ok())->toBeTrue();
});
