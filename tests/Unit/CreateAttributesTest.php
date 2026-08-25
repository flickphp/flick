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

function simpleDefinition(): array
{
    return [
        'action' => '/original',
        'method' => 'POST',
        'attributes' => ['id' => 'original-id'],
        'button' => ['text' => 'Original'],
        'fields' => [
            'email' => ['type' => 'email', 'label' => 'Email'],
        ],
    ];
}

// ---------------------------------------------------------------------------
// array definitions
// ---------------------------------------------------------------------------

it('applies an action override to an array definition', function () {
    $html = $this->form->create(simpleDefinition(), ['action' => '/overridden']);

    expect($html)->toContain('action="/overridden"');
    expect($html)->not->toContain('action="/original"');
});

it('applies an id override to an array definition', function () {
    $html = $this->form->create(simpleDefinition(), ['id' => 'overridden-id']);

    expect($html)->toContain('id="overridden-id"');
    expect($html)->not->toContain('id="original-id"');
});

it('applies a method override to an array definition', function () {
    $html = $this->form->create(simpleDefinition(), ['method' => 'GET']);

    expect($html)->toContain('method="GET"');
});

it('applies a button text override to an array definition', function () {
    $html = $this->form->create(simpleDefinition(), ['button' => 'Overridden']);

    expect($html)->toContain('Overridden');
    expect($html)->not->toContain('>Original<');
});

it('leaves untouched keys alone when overriding one', function () {
    $html = $this->form->create(simpleDefinition(), ['action' => '/overridden']);

    // the button and the fields came from the definition and must survive
    expect($html)->toContain('Original');
    expect($html)->toContain('name="email"');
});

it('renders the definition unchanged when no attributes are passed', function () {
    $html = $this->form->create(simpleDefinition());

    expect($html)->toContain('action="/original"');
    expect($html)->toContain('id="original-id"');
    expect($html)->toContain('Original');
});

// ---------------------------------------------------------------------------
// file-loaded definitions
// ---------------------------------------------------------------------------

it('applies overrides to a form loaded from a file', function () {
    $html = $this->form->create('/login', [
        'action' => '/auth/login',
        'button' => 'Sign In',
        'id' => 'my-login-form',
    ]);

    expect($html)->toContain('action="/auth/login"');
    expect($html)->toContain('id="my-login-form"');
    expect($html)->toContain('Sign In');
    expect($html)->not->toContain('id="form-login"');
});

it('keeps the shipped fields when overriding a file form', function () {
    $html = $this->form->create('/login', ['action' => '/auth/login']);

    expect($html)->toContain('name="username"');
    expect($html)->toContain('name="password"');
});

it('renders a file form unchanged when no attributes are passed', function () {
    $html = $this->form->create('/login');

    expect($html)->toContain('id="form-login"');
    expect($html)->toContain('Login');
});

// ---------------------------------------------------------------------------
// the string syntax must keep working exactly as before
// ---------------------------------------------------------------------------

it('still applies overrides to the string syntax', function () {
    $html = $this->form->create('Search|search', [
        'id' => 'form-search',
        'method' => 'GET',
        'action' => '/search',
        'button' => 'Go',
    ]);

    expect($html)->toContain('action="/search"');
    expect($html)->toContain('method="GET"');
    expect($html)->toContain('id="form-search"');
    expect($html)->toContain('Go');
});
