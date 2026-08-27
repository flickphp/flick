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

/*
|--------------------------------------------------------------------------
| Configured form action
|--------------------------------------------------------------------------
|
| `action` is a documented config key and is already honoured by multistep
| stepUrl() and by ServiceManager, but the form tag ignored it: Build::open()
| filled an unspecified action from the request path without ever consulting
| config. A documented key that silently does nothing.
|
*/

it('knows when an action was configured', function () {
    $configured = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '/submit']);
    $default = formWithServer(['REQUEST_URI' => '/contact']);

    expect($configured->hasConfiguredAction())->toBeTrue()
        ->and($default->hasConfiguredAction())->toBeFalse();
});

it('does not treat an empty configured action as configured', function () {
    $form = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '']);

    expect($form->hasConfiguredAction())->toBeFalse();
});

it('uses a configured action for open()', function () {
    $form = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '/submit']);

    expect(actionAttribute($form->open()))->toBe('/submit');
});

it('uses a configured action for create()', function () {
    $form = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '/submit']);

    expect(actionAttribute($form->create('Name, Email')))->toBe('/submit');
});

it('uses a configured action for openMultipart()', function () {
    $form = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '/submit']);

    expect(actionAttribute($form->openMultipart()))->toBe('/submit');
});

it('lets an explicit argument beat a configured action', function () {
    $form = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '/submit']);

    expect(actionAttribute($form->open('/elsewhere')))->toBe('/elsewhere');
});

it('keeps a query string the developer put in the configured action', function () {
    // The default action drops the query string on purpose; one written by
    // hand is the developer's own instruction and is passed through whole.
    $form = formWithServer(['REQUEST_URI' => '/contact'], ['action' => '/submit?ref=email']);

    expect(actionAttribute($form->open()))->toBe('/submit?ref=email');
});

it('still drops the query string when no action is configured', function () {
    $form = formWithServer(['REQUEST_URI' => '/contact.php?ref=email']);

    expect(actionAttribute($form->open()))->toBe('/contact.php');
});

it('does not let a configured action change redirect()', function () {
    // `action` says where the form posts. redirect() with no argument means
    // "back to the page that was submitted", which is a different question --
    // and the multistep flow depends on it resolving to the request path.
    $form = new Flick([
        'request' => new ArrayRequest([
            'post' => ['_id' => 'myForm'],
            'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/signup'],
        ]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
        'action' => '/somewhere-else',
        'onRedirect' => fn ($response) => $response->getUrl(),
    ]);

    expect((string) $form->redirect())->toBe('/signup');
});
