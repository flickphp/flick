<?php

use Flick\Flick;

describe('Form ID Synchronization', function () {

    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    it('syncs form id from prebuilt form definition to flick instance', function () {
        $form = new Flick([
            'id' => 'myCustomId',
            'csrf' => false,
            'echo' => false,
        ]);

        // Before create(), id should be the constructor value
        expect($form->id)->toBe('myCustomId');

        // Create the login form which has id => 'form-login'
        $form->create('/login');

        // After create(), id should be synced from the form definition
        expect($form->id)->toBe('form-login');
    });

    it('uses constructor id when form has no id attribute', function () {
        $form = new Flick([
            'id' => 'myCustomId',
            'csrf' => false,
            'echo' => false,
        ]);

        // Create an inline form array without an id attribute
        $formArray = [
            'action' => '/',
            'method' => 'POST',
            'fields' => [
                'name' => [
                    'name' => 'name',
                    'label' => 'Name',
                ],
            ],
            'button' => ['text' => 'Submit'],
        ];

        $form->create($formArray);

        // Should retain the constructor id
        expect($form->id)->toBe('myCustomId');
    });

    it('includes correct _id hidden field in form HTML', function () {
        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $html = $form->create('/login');

        // Should contain hidden _id field with the form's id
        expect($html)->toContain('name="_id" value="form-login"');
    });
});

describe('Form Submission Detection', function () {

    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';
    });

    afterEach(function () {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    it('returns true when correct form is submitted', function () {
        $_POST['_id'] = 'form-login';

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');

        expect($form->submitted())->toBeTrue();
    });

    it('returns false when different form is submitted', function () {
        $_POST['_id'] = 'some-other-form';

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');

        expect($form->submitted())->toBeFalse();
    });

    it('returns false when form is not submitted', function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');

        expect($form->submitted())->toBeFalse();
    });
});

describe('Validation and Error Handling', function () {

    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';
    });

    afterEach(function () {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    it('validates required fields from prebuilt form', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => '',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $form->request('/login');

        expect($form->hasError('username'))->toBeTrue();
        expect($form->hasError('password'))->toBeTrue();
    });

    it('returns validated data when validation passes', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => 'testuser',
            'password' => 'testpass',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $data = $form->request('/login');

        expect($data)->toBeArray();
        expect($form->ok())->toBeTrue();
    });

    it('adds custom error messages from form definition', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => '',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $form->request('/login');

        // Login form has custom message: 'Please enter your username'
        expect($form->getError('username'))->toBe('Please enter your username');
        expect($form->getError('password'))->toBe('Please enter your password');
    });

    it('validates multiple rules on single field', function () {
        $_POST = [
            '_id' => 'form-contact',
            'name' => 'Test Name',  // min:5 satisfied
            'email' => 'invalid-email',  // fails email validation
            'body' => 'Test message',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/shortContact');
        $form->request('/shortContact');

        // Email field has rules: ['required', 'email']
        // Should fail email validation
        expect($form->hasError('email'))->toBeTrue();
        expect($form->getError('email'))->toBe('Please enter a valid email address');
    });
});

describe('Error Display Integration', function () {

    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';
    });

    afterEach(function () {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    it('makes errors available via getErrors after validation', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => '',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $form->request('/login');

        $errors = $form->getErrors();

        expect($errors)->toBeArray();
        expect($errors)->toHaveKey('username');
        expect($errors)->toHaveKey('password');
    });

    it('makes errors available via getError for specific field', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => 'validpass',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $form->request('/login');

        expect($form->getError('username'))->toBe('Please enter your username');
        expect($form->getError('password'))->toBe('');
    });

    it('returns ok false when validation fails', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => '',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $form->request('/login');

        expect($form->ok())->toBeFalse();
    });

    it('returns ok true when validation passes', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => 'testuser',
            'password' => 'testpass',
        ];

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $form->create('/login');
        $form->request('/login');

        expect($form->ok())->toBeTrue();
    });
});

describe('Multiple Forms on Same Page', function () {

    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';
    });

    afterEach(function () {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    it('validates only the submitted form when multiple exist', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => '',
        ];

        $loginForm = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $contactForm = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $loginForm->create('/login');
        $contactForm->create('/shortContact');

        // Only login form should detect submission
        expect($loginForm->submitted())->toBeTrue();
        expect($contactForm->submitted())->toBeFalse();

        // Validate both
        $loginForm->request('/login');
        $contactData = $contactForm->request('/shortContact');

        // Login form should have errors
        expect($loginForm->hasError('username'))->toBeTrue();

        // Contact form should return null (not submitted)
        expect($contactData)->toBeNull();
    });

    it('isolates errors between form instances', function () {
        $_POST = [
            '_id' => 'form-login',
            'username' => '',
            'password' => '',
        ];

        $loginForm = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $contactForm = new Flick([
            'csrf' => false,
            'echo' => false,
        ]);

        $loginForm->create('/login');
        $contactForm->create('/shortContact');

        $loginForm->request('/login');

        // Login form should have errors
        expect($loginForm->getErrors())->not->toBeEmpty();

        // Contact form should have no errors (separate instance)
        expect($contactForm->getErrors())->toBeEmpty();
    });
});
