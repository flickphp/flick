<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Http\NativeRequest;

describe('Request Adapter Integration', function () {
    beforeEach(function () {
        Flick::resetDefaultRequest();
    });

    afterEach(function () {
        Flick::resetDefaultRequest();
    });

    test('Flick uses NativeRequest by default', function () {
        $form = new Flick(['csrf' => false]);

        expect($form->request)->toBeInstanceOf(NativeRequest::class);
    });

    test('Flick accepts ArrayRequest via config', function () {
        $request = ArrayRequest::createPost(['name' => 'John']);

        $form = new Flick([
            'csrf' => false,
            'request' => $request,
        ]);

        expect($form->request)->toBe($request);
    });

    test('Flick::setDefaultRequest sets default for all instances', function () {
        $request = ArrayRequest::createPost(['email' => 'test@example.com']);

        Flick::setDefaultRequest($request);

        $form1 = new Flick(['csrf' => false]);
        $form2 = new Flick(['csrf' => false]);

        expect($form1->request)->toBe($request);
        expect($form2->request)->toBe($request);
    });

    test('config request overrides static default', function () {
        $defaultRequest = ArrayRequest::createPost(['name' => 'Default']);
        $configRequest = ArrayRequest::createPost(['name' => 'Config']);

        Flick::setDefaultRequest($defaultRequest);

        $form = new Flick([
            'csrf' => false,
            'request' => $configRequest,
        ]);

        expect($form->request)->toBe($configRequest);
    });

    test('form validation works with ArrayRequest', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'testForm',
            'email' => 'invalid-email',
            'name' => '',
        ]);

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'request' => $request,
        ]);

        $form->request('email', ['email'], ['email' => 'Invalid email format']);
        $form->request('name', ['required'], ['required' => 'Name is required']);

        expect($form->hasError('email'))->toBeTrue();
        expect($form->getError('email'))->toBe('Invalid email format');
        expect($form->hasError('name'))->toBeTrue();
        expect($form->getError('name'))->toBe('Name is required');
    });

    test('form validation passes with valid ArrayRequest data', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'testForm',
            'email' => 'valid@example.com',
            'name' => 'John Doe',
        ]);

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'request' => $request,
        ]);

        $form->request('email', ['email']);
        $form->request('name', ['required']);

        expect($form->hasError('email'))->toBeFalse();
        expect($form->hasError('name'))->toBeFalse();
    });

    test('value retrieval works with ArrayRequest POST data', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'myForm',
            'username' => 'johndoe',
        ]);

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
            'request' => $request,
        ]);

        expect($form->value('username'))->toBe('johndoe');
    });

    test('value retrieval works with ArrayRequest GET data', function () {
        // a GET form Flick rendered submits its hidden _id alongside the fields
        $request = ArrayRequest::createGet()
            ->setQuery(['_id' => 'myForm', 'page' => '5']);

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
            'request' => $request,
        ]);

        expect($form->value('page'))->toBe('5');
    });

    test('value retrieval ignores GET data that is not this form\'s submission', function () {
        // a visitor following '?page=5' has not submitted anything, so the query
        // string must not be read back into the form's fields
        $request = ArrayRequest::createGet()
            ->setQuery(['page' => '5']);

        $form = new Flick([
            'csrf' => false,
            'echo' => false,
            'request' => $request,
        ]);

        expect($form->value('page'))->toBe('');
    });

    test('submitted() returns true for POST ArrayRequest', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'myForm',
            'field' => 'value',
        ]);

        $form = new Flick([
            'csrf' => false,
            'request' => $request,
        ]);

        expect($form->submitted())->toBeTrue();
    });

    test('submitted() returns false for GET ArrayRequest with no data', function () {
        $request = ArrayRequest::createGet();

        $form = new Flick([
            'csrf' => false,
            'request' => $request,
        ]);

        expect($form->submitted())->toBeFalse();
    });

    test('honeypot validation works with ArrayRequest', function () {
        $handlerCalled = false;

        $request = ArrayRequest::createPost([
            'website' => 'spam-value', // Honeypot filled
        ]);

        $form = new Flick([
            'csrf' => false,
            'honeypot' => 'website',
            'request' => $request,
            'onHoneypot' => function () use (&$handlerCalled) {
                $handlerCalled = true;

                return null;
            },
        ]);

        expect($handlerCalled)->toBeTrue();
    });

    test('AJAX detection works with ArrayRequest', function () {
        $request = ArrayRequest::createAjax([
            'data' => 'value',
        ]);

        expect($request->isAjax())->toBeTrue();
    });

    test('matches validation rule works with ArrayRequest', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'testForm',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'request' => $request,
        ]);

        $form->request('password', ['matches:password_confirmation']);

        expect($form->hasError('password'))->toBeFalse();
    });

    test('matches validation rule fails with mismatched ArrayRequest data', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'testForm',
            'password' => 'secret123',
            'password_confirmation' => 'different456',
        ]);

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'request' => $request,
        ]);

        $form->request('password', ['matches:password_confirmation'], ['matches' => 'Passwords must match']);

        expect($form->hasError('password'))->toBeTrue();
        expect($form->getError('password'))->toBe('Passwords must match');
    });

    test('confirmed validation rule works with ArrayRequest', function () {
        $request = ArrayRequest::createPost([
            '_id' => 'testForm',
            'email' => 'test@example.com',
            'email_confirmation' => 'test@example.com',
        ]);

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'request' => $request,
        ]);

        $form->request('email', ['confirmed']);

        expect($form->hasError('email'))->toBeFalse();
    });

    test('trustedProxies config reaches the native request', function () {
        $serverBackup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);

        try {
            $form = new Flick([
                'csrf' => false,
                'trustedProxies' => ['203.0.113.7'],
            ]);

            expect($form->request->isSecure())->toBeTrue();
        } finally {
            $_SERVER = $serverBackup;
        }
    });

    test('strict trustedProxies config disables forwarded-header trust', function () {
        $serverBackup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);

        try {
            $form = new Flick([
                'csrf' => false,
                'trustedProxies' => [],
            ]);

            expect($form->request->isSecure())->toBeFalse();
        } finally {
            $_SERVER = $serverBackup;
        }
    });
});
