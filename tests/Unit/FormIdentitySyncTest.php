<?php

// The form id arriving via create()/createAndValidate() attributes was only
// read by Build::open() during rendering — after the definition was stored
// and after submitted() had already compared the posted _id against the stale
// constructor id. A form given its id through attributes stored its definition
// under 'myForm' and silently never validated. These tests pin the fix: the
// id is synced before anything keys off it, and the definition is stored
// under the final id (a form file may carry its own).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $_GET = [];
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

it('stores the definition under the id passed in create() attributes', function () {
    $this->form->create('Name, Email', ['id' => 'contact']);

    $definitions = $_SESSION['flick']['_form_definitions'] ?? [];

    expect($definitions)->toHaveKey('contact')
        ->and($definitions)->not->toHaveKey('myForm');
});

it('finds the stored definition when request() follows create() with an attributes id', function () {
    $_POST['_id'] = 'contact';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $this->form->create('Name, Email', ['id' => 'contact']);
    $data = $this->form->request();

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('John')
        ->and($data['email'])->toBe('john@example.com');
});

it('finds the stored definition when request() follows create() with a form file', function () {
    $_POST['_id'] = 'form-login';
    $_POST['username'] = 'johndoe';
    $_POST['password'] = 'secret';

    $this->form->create('/login');
    $data = $this->form->request();

    // login.php names itself 'form-login'; the definition must be stored
    // under that id, not the constructor default
    expect($data)->toBeArray()
        ->and($data['username'])->toBe('johndoe')
        ->and($this->form->ok())->toBeTrue();
});

it('validates a POST when createAndValidate() gets its id from attributes', function () {
    $_POST['_id'] = 'contact-form';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $called = false;

    $data = $this->form->createAndValidate('Name, Email', ['id' => 'contact-form'], function () use (&$called) {
        $called = true;
    });

    expect($called)->toBeTrue()
        ->and($data)->toBeArray()
        ->and($data['name'])->toBe('John');
});

it('validates a POST when renderValidated() gets its id from attributes', function () {
    $_POST['_id'] = 'contact-form';
    $_POST['name'] = 'John';
    $_POST['email'] = 'john@example.com';

    $called = false;

    $out = $this->form->renderValidated('Name, Email', ['id' => 'contact-form'], function () use (&$called) {
        $called = true;
    });

    expect($called)->toBeTrue()
        ->and($out)->toContain('Thank you for filling out our form!');
});

it('keeps the id, the config and Support in step through one writer', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);

    $form->adoptFormId('contact');

    // Support::$config is protected and has no public reader - only
    // setConfigValue(). Bind a closure to reach it without widening the class,
    // the same way tests/Auth/Unit/AuthTest.php reads Auth::$session.
    $supportId = (fn () => $this->config['id'])->call($form->support);

    expect($form->id)->toBe('contact')
        ->and($form->config('id'))->toBe('contact')
        ->and($supportId)->toBe('contact');
});
