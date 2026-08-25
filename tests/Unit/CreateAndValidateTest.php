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

it('returns null when form is not submitted', function () {
    $result = $this->form->createAndValidate('Name, Email');

    expect($result)->toBeNull();
});

it('returns data array when form is submitted with multiple fields', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John Doe';
    $_POST['email'] = 'john@example.com';

    $result = $this->form->createAndValidate('Name, Email');

    expect($result)->toBeArray()
        ->and($result['name'])->toBe('John Doe')
        ->and($result['email'])->toBe('john@example.com');
});

it('handles single field when POST key matches field name case', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    // Single field without comma uses the field name as-is for POST lookup
    $_POST['Name'] = 'John Doe';

    $result = $this->form->createAndValidate('Name');

    expect($result)->toBeString()
        ->and($result)->toBe('John Doe');
});

it('outputs success message when validation passes', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    // Create a fresh form with echo enabled from start
    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name, Email');
    $output = ob_get_clean();

    expect($output)->toContain('Thank you for filling out our form!');
});

it('outputs error message when validation fails', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = '';

    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name[required], Email[required]');
    $output = ob_get_clean();

    expect($output)->toContain('Please fix the errors');
});

it('hides form on success when hideOnSuccess is true by default', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name, Email');
    $output = ob_get_clean();

    // Should NOT contain form tag when hideOnSuccess is true (default)
    expect($output)->not->toContain('<form')
        ->and($output)->toContain('Thank you for filling out our form!');
});

it('shows form on success when hideOnSuccess is false', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name, Email', [], null, 'Thank you for filling out our form!', 'Error', hideOnSuccess: false);
    $output = ob_get_clean();

    // Should contain both form and success message
    expect($output)->toContain('<form')
        ->and($output)->toContain('Thank you for filling out our form!');
});

it('accepts custom success message', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name, Email', [], null, successMessage: 'Form submitted successfully!');
    $output = ob_get_clean();

    expect($output)->toContain('Form submitted successfully!');
});

it('accepts custom error message', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = '';

    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name[required], Email', [], null, 'Thank you for filling out our form!', errorMessage: 'Validation failed');
    $output = ob_get_clean();

    expect($output)->toContain('Validation failed');
});

it('calls onSuccess callback only when validation passes', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $callbackCalled = false;
    $callbackData = null;

    $this->form->createAndValidate('Name, Email', [], onSuccess: function ($data) use (&$callbackCalled, &$callbackData) {
        $callbackCalled = true;
        $callbackData = $data;
    });

    expect($callbackCalled)->toBeTrue()
        ->and($callbackData)->toBeArray()
        ->and($callbackData['name'])->toBe('John')
        ->and($callbackData['email'])->toBe('john@example.com');
});

it('does not call onSuccess callback when validation fails', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = '';

    $callbackCalled = false;

    $this->form->createAndValidate('Name[required], Email[required]', [], onSuccess: function ($data) use (&$callbackCalled) {
        $callbackCalled = true;
    });

    expect($callbackCalled)->toBeFalse();
});

it('validates with string field definitions containing rules', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $result = $this->form->createAndValidate('Name[required], Email[email]');

    expect($result)->toBeArray()
        ->and($this->form->ok())->toBeTrue();
});

it('works with array field definitions', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';

    $result = $this->form->createAndValidate([
        'fields' => [
            'name' => ['label' => 'Name', 'rules' => ['required']],
        ],
    ]);

    expect($result)->toBeArray()
        ->and($this->form->ok())->toBeTrue();
});

it('adds validation errors when rules fail', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['email'] = 'not-an-email';
    $_POST['name'] = '';

    $result = $this->form->createAndValidate('Name, Email[email]');

    expect($result)->toBeArray()
        ->and($this->form->ok())->toBeFalse()
        ->and($this->form->hasError('email'))->toBeTrue();
});

it('returns data even when validation fails', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = 'invalid';

    $result = $this->form->createAndValidate('Name[required], Email[email]');

    expect($result)->toBeArray()
        ->and($this->form->ok())->toBeFalse();
});

it('generates form html when not submitted', function () {
    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name, Email');
    $output = ob_get_clean();

    expect($output)->toContain('<form')
        ->and($output)->toContain('name="name"')
        ->and($output)->toContain('name="email"');
});

it('passes attributes to the form', function () {
    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name, Email', ['id' => 'contact-form', 'action' => '/submit']);
    $output = ob_get_clean();

    expect($output)->toContain('id="contact-form"')
        ->and($output)->toContain('action="/submit"');
});

it('shows form with error message when validation fails', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['name'] = '';
    $_POST['email'] = '';

    $form = new Flick([
        'csrf' => false,
        'echo' => true,
    ]);

    ob_start();
    $form->createAndValidate('Name[required], Email');
    $output = ob_get_clean();

    // Form should be shown when there are errors
    expect($output)->toContain('<form')
        ->and($output)->toContain('Please fix the errors');
});
