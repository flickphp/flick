<?php

/**
 * The 'rules' attribute accepts a string, not only an array.
 *
 * normalizeRules() in Pro's validation adapter documents three interchangeable
 * shapes for this key - map, list, and comma-separated string - and core's own
 * Validate::convertRulesToArray() takes string|array. isRequired() was the one
 * reader that assumed an array, so a string blew up with
 * "in_array(): Argument #2 ($haystack) must be of type array, string given"
 * before the field ever rendered.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_SESSION = [];

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    $_POST = [];
    $_SESSION = [];
});

it('renders a field whose rules attribute is a string', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => 'required']);

    expect($html)->toContain('name="name"');
});

it('marks a field required from a single-rule string', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => 'required']);

    expect($html)->toContain('required');
});

it('marks a field required from a multi-rule string', function () {
    $html = $this->form->email('email', 'Email', '', ['rules' => 'required,email']);

    expect($html)->toContain('required');
});

it('honors the r alias inside a rules string', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => 'r,min:3']);

    expect($html)->toContain('required');
});

it('tolerates spaces around the separators', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => ' required , min:3 ']);

    expect($html)->toContain('required');
});

it('does not mark a field required when the string carries no required rule', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => 'email,min:3']);

    expect($html)->not->toContain('required');
});

it('does not treat a rule argument as the required rule', function () {
    // 'in:required,other' has 'required' as an ARGUMENT, not a rule name.
    $html = $this->form->text('name', 'Name', '', ['rules' => 'in:required,other']);

    expect($html)->not->toContain('required');
});

it('still handles the list form', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => ['required', 'email']]);

    expect($html)->toContain('required');
});

it('still handles the map form', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => ['required' => true, 'email' => true]]);

    expect($html)->toContain('required');
});

it('still leaves a list without required unmarked', function () {
    $html = $this->form->text('name', 'Name', '', ['rules' => ['email', 'min:3']]);

    expect($html)->not->toContain('required');
});

it('carries a string rules attribute through to getFields', function () {
    $this->form->text('email', 'Email', '', ['rules' => 'required,email']);

    expect($this->form->getFields())->toHaveKey('email')
        ->and($this->form->getFields()['email']['rules'])->toBe('required,email');
});
