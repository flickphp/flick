<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Default form action
|--------------------------------------------------------------------------
|
| open() fills an omitted action with the path the request arrived on. It used
| to read SCRIPT_NAME, which only names the current URL when that URL maps to a
| PHP file. Behind a front controller — Laravel, or any rewrite to index.php —
| SCRIPT_NAME is always '/index.php', so a form rendered at /contact posted to
| /index.php and the code meant to handle it never ran.
|
*/

function formWithServer(array $server, array $config = []): Flick
{
    return new Flick(array_merge([
        'request' => new ArrayRequest(['server' => $server]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ], $config));
}

function actionAttribute(string $html): string
{
    preg_match('/<form[^>]*\baction="([^"]*)"/', $html, $matches);

    return $matches[1] ?? '';
}

it('posts back to the current path behind a front controller', function () {
    $form = formWithServer([
        'REQUEST_URI' => '/contact',
        'SCRIPT_NAME' => '/index.php',
        'PHP_SELF' => '/index.php',
    ]);

    expect(actionAttribute($form->open()))->toBe('/contact');
});

it('posts back to the script when the url is the php file itself', function () {
    $form = formWithServer([
        'REQUEST_URI' => '/contact.php',
        'SCRIPT_NAME' => '/contact.php',
        'PHP_SELF' => '/contact.php',
    ]);

    expect(actionAttribute($form->open()))->toBe('/contact.php');
});

it('drops the query string from the default action', function () {
    $form = formWithServer([
        'REQUEST_URI' => '/contact.php?ref=email&utm=x',
        'SCRIPT_NAME' => '/contact.php',
    ]);

    expect(actionAttribute($form->open()))->toBe('/contact.php');
});

it('drops a multistep step parameter from the default action', function () {
    // createMultistep() only validates and advances a step while no ?step= is
    // present, so an action carrying one forward would stall the flow.
    $form = formWithServer([
        'REQUEST_URI' => '/signup?step=Contact',
        'SCRIPT_NAME' => '/index.php',
    ]);

    expect(actionAttribute($form->open()))->toBe('/signup');
});

it('handles a request uri written in absolute form', function () {
    $form = formWithServer([
        'REQUEST_URI' => 'http://example.test/contact?ref=email',
        'SCRIPT_NAME' => '/index.php',
    ]);

    expect(actionAttribute($form->open()))->toBe('/contact');
});

it('falls back to the site root when the request carries no uri', function () {
    $form = formWithServer([
        'REQUEST_URI' => '',
        'SCRIPT_NAME' => '/index.php',
    ]);

    expect(actionAttribute($form->open()))->toBe('/');
});

it('keeps an explicit action', function () {
    $form = formWithServer([
        'REQUEST_URI' => '/contact',
        'SCRIPT_NAME' => '/index.php',
    ]);

    expect(actionAttribute($form->open('/submit')))->toBe('/submit');
});

it('gives create() the same default action as open()', function () {
    $form = formWithServer([
        'REQUEST_URI' => '/contact',
        'SCRIPT_NAME' => '/index.php',
    ]);

    expect(actionAttribute($form->create('Name, Email')))->toBe('/contact');
});

/*
| redirect() with no target resolves the same way. It used to read PHP_SELF,
| which names the front controller rather than the route, so a multistep form
| redirected to /index.php after every completed step.
*/

function redirectTarget(array $server): string
{
    $server['REQUEST_METHOD'] = 'POST';

    $form = new Flick([
        'request' => new ArrayRequest(['post' => ['_id' => 'myForm'], 'server' => $server]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
        'onRedirect' => fn ($response) => $response->getUrl(),
    ]);

    return (string) $form->redirect();
}

it('redirects to the current path behind a front controller', function () {
    expect(redirectTarget([
        'REQUEST_URI' => '/signup',
        'SCRIPT_NAME' => '/index.php',
        'PHP_SELF' => '/index.php',
    ]))->toBe('/signup');
});

it('redirects to the script when the url is the php file itself', function () {
    expect(redirectTarget([
        'REQUEST_URI' => '/signup.php',
        'SCRIPT_NAME' => '/signup.php',
        'PHP_SELF' => '/signup.php',
    ]))->toBe('/signup.php');
});

it('drops a step parameter from the redirect target', function () {
    // The post-step redirect exists to clear ?step= from the url; carrying it
    // through would leave createMultistep() looking at the same step forever.
    expect(redirectTarget([
        'REQUEST_URI' => '/signup?step=Contact',
        'SCRIPT_NAME' => '/index.php',
        'PHP_SELF' => '/index.php',
    ]))->toBe('/signup');
});

it('keeps an explicit redirect target', function () {
    $form = new Flick([
        'request' => new ArrayRequest([
            'post' => ['_id' => 'myForm'],
            'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/signup'],
        ]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
        'onRedirect' => fn ($response) => $response->getUrl(),
    ]);

    expect($form->redirect('/thank-you'))->toBe('/thank-you');
});
