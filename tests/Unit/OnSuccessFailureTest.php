<?php

use Flick\Flick;

/*
 * The guide teaches saving inside onSuccess (guide/creating-forms.md,
 * "Processing the Data") and promises the success message and the hidden
 * form for a successful submission. It does not say what happens when the
 * callback's work fails. A Flick service reports a runtime failure by adding
 * to the error bag and returning false, so the callbacks here do the same
 * through the documented addError().
 */

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = [];
    $_POST = [
        '_id' => 'myForm',
        'name' => 'John',
        'email' => 'john@example.com',
    ];
});

afterEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];
});

function failingSave(Flick $form): Closure
{
    return function () use ($form): bool {
        $form->addError('sql', 'A database error occurred');

        return false;
    };
}

it('does not show the success message when the onSuccess callback records an error', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);

    $output = $form->renderValidated('Name, Email', onSuccess: failingSave($form));

    expect($output)
        ->not->toContain('Thank you for filling out our form!')
        ->toContain('Please fix the errors');
});

it('keeps the form on the page with the submitted values when the onSuccess callback records an error', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);

    $output = $form->renderValidated('Name, Email', onSuccess: failingSave($form));

    expect($output)
        ->toContain('<form')
        ->toContain('value="John"')
        ->toContain('value="john@example.com"');
});

it('echoes the error message and the form from createAndValidate when the onSuccess callback records an error', function () {
    $form = new Flick(['csrf' => false, 'echo' => true]);

    ob_start();
    $data = $form->createAndValidate('Name, Email', onSuccess: failingSave($form));
    $output = ob_get_clean();

    expect($output)
        ->not->toContain('Thank you for filling out our form!')
        ->toContain('Please fix the errors')
        ->toContain('<form')
        ->and($data)->toBe(['name' => 'John', 'email' => 'john@example.com'])
        ->and($form->ok())->toBeFalse();
});

it('does not show the success message above a kept form when the onSuccess callback records an error', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);

    $output = $form->renderValidated('Name, Email', onSuccess: failingSave($form), hideOnSuccess: false);

    expect($output)
        ->not->toContain('Thank you for filling out our form!')
        ->toContain('Please fix the errors')
        ->toContain('<form');
});

it('still shows the success message and hides the form when the onSuccess callback succeeds', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);

    $saved = null;
    $output = $form->renderValidated('Name, Email', onSuccess: function ($data) use (&$saved) {
        $saved = $data;
    });

    expect($saved)->toBe(['name' => 'John', 'email' => 'john@example.com'])
        ->and($output)
        ->toContain('Thank you for filling out our form!')
        ->not->toContain('Please fix the errors')
        ->not->toContain('<form');
});
