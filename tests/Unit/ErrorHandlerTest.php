<?php

use Flick\Flick;
use Flick\Support\Errors;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $this->form = new Flick([
    ]);
});

describe('error contract (Errors::add)', function () {
    it('lets a custom string message passed with a rule override the canned text', function () {
        $errors = new Errors;

        $errors->add('email', 'Please provide your email address.', 'required');

        expect($errors->get('email'))->toBe('Please provide your email address.');
    });

    it('coerces an array message without a rule to a string so get() cannot TypeError', function () {
        $errors = new Errors;

        $errors->add('name', ['First error.', 'Second error.']);

        expect($errors->get('name'))->toBe('First error. Second error.');
    });

    it('still resolves a per-rule message map with placeholders', function () {
        $errors = new Errors;

        $errors->add('name', ['required' => ':key is definitely required'], 'required');

        expect($errors->get('name'))->toBe('name is definitely required');
    });
});

it('adds an error to the errors array', function () {
    $this->form->addError('name', 'Name is required');

    expect($this->form->hasError('name'))
        ->toBeTrue()
        ->and($this->form->getError('name'))
        ->toBe('Name is required');
});

it('removes an error from the errors array', function () {
    $this->form->addError('name', '');

    $this->form->deleteError('name');

    expect($this->form->getErrors())
        ->toBeEmpty();
});

it('checks if a key is in the errors array', function () {
    $this->form->addError('name', 'Name is required');

    expect($this->form->getErrors())
        ->toHaveKey('name');
});

it('errorsIsEmpty returns correct boolean', function () {
    // No errors → should be true
    expect($this->form->errorsIsEmpty())->toBeTrue();
    expect($this->form->errorsIsNotEmpty())->toBeFalse();

    // After adding an error → should flip
    $this->form->addError('field', 'Error message');
    expect($this->form->errorsIsEmpty())->toBeFalse();
    expect($this->form->errorsIsNotEmpty())->toBeTrue();
});
