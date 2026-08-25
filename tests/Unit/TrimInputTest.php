<?php

use Flick\Flick;

beforeEach(function () {
    $_POST = [];
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';

    $this->form = new Flick([
        'csrf' => false,
    ]);
});

afterEach(function () {

    $_POST = [];
    $_GET = [];
    unset($_SERVER['REQUEST_METHOD']);
});

it('trims surrounding whitespace before rules run', function () {
    $_POST['email'] = '  a@b.com  ';

    $value = $this->form->request('email', ['required', 'email']);

    expect($this->form->hasError('email'))->toBeFalse();
    expect($value)->toBe('a@b.com');
});

it('validates the raw value when trim is disabled', function () {
    $form = new Flick([
        'csrf' => false,
        'trim' => false,
    ]);
    $_POST['email'] = '  a@b.com  ';

    $value = $form->request('email', ['required', 'email'], ['email' => 'ERROR']);

    expect($form->hasError('email'))->toBeTrue();
    expect($value)->toBe('  a@b.com  ');
});

it('leaves password fields untrimmed', function () {
    $_POST['password'] = '  secret  ';
    $_POST['password_confirmation'] = '  secret  ';

    $password = $this->form->request('password', ['required']);
    $confirmation = $this->form->request('password_confirmation', ['required']);

    expect($password)->toBe('  secret  ');
    expect($confirmation)->toBe('  secret  ');
});

it('trims each value of a multi-value field', function () {
    $_POST['colors'] = [' red ', 'blue '];

    $value = $this->form->request('colors[]', ['required']);

    expect($value)->toBe(['red', 'blue']);
});

it('compares matches against the trimmed counterpart', function () {
    $_POST['code'] = ' abc ';
    $_POST['code_repeat'] = 'abc  ';

    $this->form->request('code', ['required', 'matches:code_repeat']);

    expect($this->form->hasError('code'))->toBeFalse();
});

it('compares confirmed against the trimmed counterpart', function () {
    $_POST['pin'] = ' 1234 ';
    $_POST['pin_confirmation'] = '1234 ';

    $this->form->request('pin', ['confirmed']);

    expect($this->form->hasError('pin'))->toBeFalse();
});

it('fails required for whitespace-only input', function () {
    $_POST['name'] = '   ';

    $this->form->request('name', ['required'], ['required' => 'ERROR']);

    expect($this->form->hasError('name'))->toBeTrue();
});
