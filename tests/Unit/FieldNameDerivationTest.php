<?php

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
    $_GET = [];
    $_SESSION = [];
});

/**
 * Pull the name="" attributes out of rendered form markup, ignoring Flick's own
 * hidden fields and anything inside the client-side validation <script> block.
 */
function renderedFieldNames(string $html): array
{
    $html = preg_replace('#<script>.*?</script>#s', '', $html);
    preg_match_all('/name="([^"]+)"/', $html, $m);

    return array_values(array_filter($m[1], fn ($n) => ! in_array($n, ['_id', '_token'], true)));
}

// ---------------------------------------------------------------------------
// request() must resolve a field name the same way create() does
// ---------------------------------------------------------------------------

it('resolves a multi-word label to the same snake_case name in create() and request()', function () {
    $_POST['_id'] = 'myForm';
    $_POST['first_name'] = 'Gern';
    $_POST['last_name'] = 'Blanston';

    expect(renderedFieldNames($this->form->create('First Name, Last Name')))
        ->toBe(['first_name', 'last_name']);

    $data = $this->form->request('First Name, Last Name');

    expect($data)->toHaveKeys(['first_name', 'last_name']);
    expect($data['first_name'])->toBe('Gern');
    expect($data['last_name'])->toBe('Blanston');
});

it('strips the element type from the field name in request()', function () {
    $_POST['_id'] = 'myForm';
    $_POST['email'] = 'gern@example.com';
    $_POST['comments'] = 'Hello there';

    $data = $this->form->request('Email|email, Comments|textarea');

    expect($data)->toHaveKeys(['email', 'comments']);
    expect($data['email'])->toBe('gern@example.com');
    expect($data['comments'])->toBe('Hello there');
});

it('strips a default value from the field name in request()', function () {
    $_POST['_id'] = 'myForm';
    $_POST['username'] = 'gern';
    $_POST['password'] = 'hunter22';

    $data = $this->form->request('Username{gern}, Password|password');

    expect($data)->toHaveKeys(['username', 'password']);
    expect($data['username'])->toBe('gern');
    expect($data['password'])->toBe('hunter22');
});

it('strips dropdown options from the field name in request()', function () {
    $_POST['_id'] = 'myForm';
    $_POST['state'] = 'TX';
    $_POST['name'] = 'Gern';

    $data = $this->form->request('Name, State|select(states)');

    expect($data)->toHaveKeys(['name', 'state']);
    expect($data['state'])->toBe('TX');
});

it('still applies inline rules once the type has been stripped', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'Gern';
    $_POST['password'] = '';

    $this->form->request('Name, Password|password[required]');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('password'))->toContain('password');
});

// ---------------------------------------------------------------------------
// auto-rules: create() then request() with no arguments
// ---------------------------------------------------------------------------

it('round trips a typed create() string through auto-rules', function () {
    $_POST['_id'] = 'myForm';
    $_POST['name'] = 'Gern';
    $_POST['email'] = 'gern@example.com';
    $_POST['comments'] = 'Hello there';

    $this->form->create('Name, Email|email, Comments|textarea');
    $data = $this->form->request();

    expect($data)->toHaveKeys(['name', 'email', 'comments']);
    expect($data['email'])->toBe('gern@example.com');
    expect($data['comments'])->toBe('Hello there');
});

it('round trips a multi-word create() string through auto-rules', function () {
    $_POST['_id'] = 'myForm';
    $_POST['first_name'] = 'Gern';
    $_POST['last_name'] = 'Blanston';

    $this->form->create('First Name, Last Name');
    $data = $this->form->request();

    expect($data['first_name'])->toBe('Gern');
    expect($data['last_name'])->toBe('Blanston');
});

// ---------------------------------------------------------------------------
// cross-field rules depend on the resolved name
// ---------------------------------------------------------------------------

it('matches a confirmation field declared with a multi-word label', function () {
    $_POST['_id'] = 'myForm';
    $_POST['password'] = 'Str0ng!Passw0rd';
    $_POST['password_confirmation'] = 'Str0ng!Passw0rd';

    $this->form->request('Password[required, confirmed], Password Confirmation[required]');

    expect($this->form->ok())->toBeTrue();
    expect($this->form->getErrors())->toBe([]);
});

it('reports a mismatched confirmation field declared with a multi-word label', function () {
    $_POST['_id'] = 'myForm';
    $_POST['password'] = 'Str0ng!Passw0rd';
    $_POST['password_confirmation'] = 'something else';

    $this->form->request('Password[required, confirmed], Password Confirmation[required]');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('password'))->not->toBe('');
});

// ---------------------------------------------------------------------------
// array-defined forms: render and read must agree on the name
// ---------------------------------------------------------------------------

it('reads an array-defined field back under its array key', function () {
    $_POST['_id'] = 'myForm';
    $_POST['full_name'] = 'Gern Blanston';

    $definition = [
        'fields' => [
            'full_name' => ['label' => 'Full Name', 'rules' => ['required']],
        ],
    ];

    expect(renderedFieldNames($this->form->create($definition)))->toBe(['full_name']);

    $data = $this->form->request($definition);

    expect($data)->toHaveKey('full_name');
    expect($data['full_name'])->toBe('Gern Blanston');
    expect($this->form->ok())->toBeTrue();
});

it('returns fieldset children as top-level keys', function () {
    $_POST['_id'] = 'myForm';
    $_POST['nickname'] = 'Gern';
    $_POST['city'] = 'Walla Walla';

    $definition = [
        'fields' => [
            'fieldset-1' => [
                'legend' => 'Details',
                'fields' => [
                    'nickname' => ['name' => 'nickname', 'label' => 'Nickname', 'rules' => ['required']],
                ],
            ],
            'city' => ['label' => 'City'],
        ],
    ];

    $data = $this->form->request($definition);

    expect($data)->toHaveKeys(['nickname', 'city'])
        ->and($data['nickname'])->toBe('Gern')
        ->and($data['city'])->toBe('Walla Walla');
});

it('collapses duplicate field names into one key like string forms do', function () {
    $_POST['_id'] = 'myForm';
    $_POST['email'] = 'gern@example.com';

    $definition = [
        'fields' => [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'email', 'label' => 'Email Again'],
        ],
    ];

    $data = $this->form->request($definition);

    expect($data)->toBe(['email' => 'gern@example.com']);
});

it('prefers an explicit name over the array key in both directions', function () {
    $_POST['_id'] = 'myForm';
    $_POST['contact_email'] = 'gern@example.com';

    $definition = [
        'fields' => [
            'email' => ['name' => 'contact_email', 'label' => 'Email', 'rules' => ['required']],
        ],
    ];

    expect(renderedFieldNames($this->form->create($definition)))->toBe(['contact_email']);

    $data = $this->form->request($definition);

    expect($data['contact_email'])->toBe('gern@example.com');
    expect($this->form->ok())->toBeTrue();
});

// ---------------------------------------------------------------------------
// the id attribute must be a valid HTML id
// ---------------------------------------------------------------------------

it('renders an id without whitespace for a multi-word label', function () {
    $html = $this->form->create('First Name');

    expect($html)->toContain('id="first_name"');
    expect($html)->toContain('for="first_name"');
    expect($html)->not->toContain('id="first name"');
    expect($html)->not->toContain('for="first name"');
});

it('renders the error container id without whitespace', function () {
    $html = $this->form->text('first name', 'First Name');

    expect($html)->toContain('has-error-first_name');
    expect($html)->not->toContain('has-error-first name');
});
