<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
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

it('stores form definition in session when create() is called', function () {
    $this->form->create('Name[min:3],Email[email]');

    $definitions = $_SESSION['flick']['_form_definitions'] ?? [];

    expect($definitions)->toHaveKey('myForm');
    expect($definitions['myForm'])->toBe('Name[min:3],Email[email]');
});

it('retrieves stored definition when request() is called without arguments', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $this->form->create('Name[min:3],Email[email]');
    $data = $this->form->request();

    expect($data)->toBeArray();
    expect($data['name'])->toBe('John');
    expect($data['email'])->toBe('john@example.com');
});

it('throws exception when request() called without args and no definition exists', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';

    // Don't call create() - go straight to request()
    $this->form->request();
})->throws(RuntimeException::class, 'No form definition found for form `myForm`');

it('allows explicit rules to override stored definition', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'Jo'; // Too short for min:5

    $this->form->create('Name[min:3]');

    // Use explicit stricter rules - note: single field returns the value directly
    $data = $this->form->request('name', 'min:5');

    // Should fail validation with min:5
    expect($this->form->ok())->toBeFalse();
    expect($this->form->hasError('name'))->toBeTrue();
});

it('supports multiple forms with different IDs', function () {
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    // Create first form
    $form1 = new Flick([
        'id' => 'contactForm',
        'csrf' => false,
        'echo' => false,
    ]);
    $form1->create('Name[min:3]');

    // Create second form
    $form2 = new Flick([
        'id' => 'subscribeForm',
        'csrf' => false,
        'echo' => false,
    ]);
    $form2->create('Email[email]');

    $definitions = $_SESSION['flick']['_form_definitions'] ?? [];

    expect($definitions)->toHaveKey('contactForm');
    expect($definitions)->toHaveKey('subscribeForm');
    expect($definitions['contactForm'])->toBe('Name[min:3]');
    expect($definitions['subscribeForm'])->toBe('Email[email]');
});

it('works with array-based form definitions', function () {
    $_POST['_id'] = 'myForm';
    $_POST['username'] = 'johndoe';

    $arrayDefinition = [
        'action' => '/submit',
        'fields' => [
            [
                'type' => 'text',
                'name' => 'username',
                'label' => 'Username',
                'rules' => ['min:3'],
            ],
        ],
    ];

    $this->form->create($arrayDefinition);
    $data = $this->form->request();

    // one map keyed by field name, same shape string-built forms return
    expect($data)->toBeArray();
    expect($data['username'])->toBe('johndoe');
});

it('validates correctly when using auto-rules', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'Jo'; // Too short for min:3
    $_POST['email'] = 'invalid-email'; // Invalid email format

    $this->form->create('Name[min:3],Email[email]');
    $this->form->request();

    expect($this->form->ok())->toBeFalse();
    expect($this->form->hasError('name'))->toBeTrue();
    expect($this->form->hasError('email'))->toBeTrue();
});

it('passes validation with valid data using auto-rules', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $this->form->create('Name[min:3],Email[email]');
    $this->form->request();

    expect($this->form->ok())->toBeTrue();
});
