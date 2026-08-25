<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm'];
    $_GET = [];

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

it('applies inline rules to a single field with no comma', function () {
    $_POST['email'] = 'not-an-email';

    $this->form->request('Email[email]');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('email'))->toContain('email');
});

it('returns the field value for a single field with inline rules', function () {
    $_POST['email'] = 'gern@example.com';

    $value = $this->form->request('Email[email]');

    expect($value)->toBe('gern@example.com');
    expect($this->form->ok())->toBeTrue();
});

it('applies several inline rules to a single field', function () {
    $_POST['username'] = 'a';

    $this->form->request('Username[required, min:3]');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('username'))->toContain('3');
});

it('applies inline rules to a single multi-word field', function () {
    $_POST['first_name'] = '';

    $this->form->request('First Name[required]');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('first_name'))->not->toBe('');
});

it('honours a custom message block on a single field', function () {
    $_POST['zip'] = '123';

    $this->form->request('zip[min:5][min:Zip is too short]');

    expect($this->form->getError('zip'))->toBe('Zip is too short');
});

it('still treats a bare single field as a plain name lookup', function () {
    $_POST['name'] = 'Gern';

    expect($this->form->request('name'))->toBe('Gern');
    expect($this->form->ok())->toBeTrue();
});

it('still accepts rules passed as the second argument', function () {
    $_POST['name'] = 'a';

    $value = $this->form->request('name', ['required', 'min:3']);

    expect($value)->toBe('a');
    expect($this->form->ok())->toBeFalse();
});

it('gives the same result with and without a trailing comma', function () {
    $_POST['email'] = 'not-an-email';

    $withoutComma = new Flick(['csrf' => false, 'echo' => false]);
    $withoutComma->request('Email[email]');

    $withComma = new Flick(['csrf' => false, 'echo' => false]);
    $withComma->request('Email[email],');

    expect($withoutComma->ok())->toBe($withComma->ok());
    expect($withoutComma->getError('email'))->toBe($withComma->getError('email'));
});

it('resolves a single field carrying an element type', function () {
    $_POST['comments'] = '';

    $this->form->request('Comments|textarea[required]');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('comments'))->not->toBe('');
});

it('unwraps a single field whose rule arguments contain a comma', function () {
    $_POST['age'] = '30';

    // 'between:18,65' has a comma, but the string still describes ONE field;
    // it must come back as the scalar value like 'Email[email]' does, not as
    // a one-entry array
    $data = $this->form->request('Age[between:18,65]');

    expect($data)->toBe('30')
        ->and($this->form->ok())->toBeTrue();
});

it('splits fields at real boundaries, not inside a regex character class', function () {
    $_POST['code'] = 'A,1';
    $_POST['name'] = 'Gern';

    // the ], inside the regex class used to end the rules block, so the comma
    // after it read as a field boundary and produced a phantom third field
    $data = $this->form->request('Code[regex:/^[A-Z],[0-9]$/], Name[required]');

    expect(array_keys($data))->toBe(['code', 'name']);
});
