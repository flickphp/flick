<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
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

it('returns the form HTML on initial load in echo=false', function () {
    $result = $this->form->renderValidated('Name[required], Email[email, required]');

    expect($result)
        ->toBeString()
        ->toContain('<form')
        ->toContain('name="name"')
        ->toContain('name="email"');
});

it('returns only the success message and hides the form on success', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $result = $this->form->renderValidated('Name, Email', successMessage: 'Thanks for signing up!');

    expect($result)
        ->toContain('Thanks for signing up!')
        ->not->toContain('<form');
});

it('passes the validated data to the onSuccess callback', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $captured = null;
    $this->form->renderValidated('Name, Email', onSuccess: function ($data) use (&$captured) {
        $captured = $data;
    });

    expect($captured)->toBeArray()
        ->and($captured['name'])->toBe('John')
        ->and($captured['email'])->toBe('john@example.com');
});

it('returns the form and an error message on validation failure', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = '';

    $result = $this->form->renderValidated('Name[required], Email[required]', errorMessage: 'Please fix the errors below');

    expect($result)
        ->toContain('<form')
        ->toContain('Please fix the errors below');
});

it('keeps the form visible on success when hideOnSuccess is false', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $result = $this->form->renderValidated('Name, Email', hideOnSuccess: false, successMessage: 'Saved!');

    expect($result)
        ->toContain('<form')
        ->toContain('Saved!');
});

it('does not run the onSuccess callback when validation fails', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = '';

    $called = false;
    $this->form->renderValidated('Name[required], Email[required]', onSuccess: function () use (&$called) {
        $called = true;
    });

    expect($called)->toBeFalse();
});

it('echoes the form in echo=true mode', function () {
    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->renderValidated('Name, Email');
    $output = ob_get_clean();

    expect($output)->toContain('<form');
});
