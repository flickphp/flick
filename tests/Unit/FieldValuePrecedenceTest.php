<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Which source wins when a field is re-rendered
|--------------------------------------------------------------------------
|
| One order, every field type: POST, then GET, then the session, then the
| developer's default. Text inputs used to let the session win over POST while
| checkboxes, radios and selects let POST win - and the session holds the value
| AFTER validation modifiers have run, so a bcrypt'd password was rendered back
| into the field for anyone to read.
|
*/

function precedenceForm(array $post, ArraySession $session, array $config = []): Flick
{
    return new Flick(array_merge([
        'request' => new ArrayRequest([
            'post' => $post,
            'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
        ]),
        'session' => $session,
        'echo' => false,
        'csrf' => false,
    ], $config));
}

it('never renders a hashed password back into the field', function () {
    $session = new ArraySession;
    $form = precedenceForm(
        ['_id' => 'myForm', 'password' => 'hunter2'],
        $session,
        ['persistToSession' => true]
    );

    $form->request('password', 'required, bcrypt');
    $rendered = $form->password('password', 'Password');

    // the session holds the post-modifier value; the field must show what the
    // visitor typed, never the hash
    expect($session->getValue('password'))->toStartWith('$2y$')
        ->and($rendered)->not->toContain('$2y$');
});

it('lets the posted value win over the session for every field type', function () {
    $session = new ArraySession;
    $session->setValue('city', 'Boulder');
    $session->setValue('terms', 'no');
    $session->setValue('plan', 'free');
    $session->setValue('size', 'small');

    $form = precedenceForm([
        '_id' => 'myForm',
        'city' => 'Denver',
        'terms' => 'yes',
        'plan' => 'pro',
        'size' => 'large',
    ], $session, ['persistToSession' => true]);

    expect($form->text('city', 'City'))->toContain('value="Denver"')
        ->and($form->checkbox('terms', 'Terms', 'yes'))->toContain('checked')
        ->and($form->radio('plan', 'Plan', 'pro'))->toContain('checked')
        ->and($form->select('size', 'Size', '', ['options' => ['small' => 'Small', 'large' => 'Large']]))
        ->toContain('<option value="large" selected>');
});

it('still repopulates from the session when the step was not posted', function () {
    // multistep back-navigation: this step's fields are absent from the POST,
    // so the session is the only source left and must still win
    $session = new ArraySession;
    $session->setValue('city', 'Boulder');

    $form = precedenceForm(['_id' => 'myForm', 'other' => 'x'], $session, ['persistToSession' => true]);

    expect($form->text('city', 'City'))->toContain('value="Boulder"');
});

it('falls back to the developer default when nothing was submitted or stored', function () {
    $form = precedenceForm(['_id' => 'myForm'], new ArraySession);

    expect($form->text('city', 'City', 'Denver'))->toContain('value="Denver"');
});
