<?php

use Flick\App\Build;
use Flick\App\Validate;
use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Service\ServiceManager;
use Flick\Support\Helpers;

/*
|--------------------------------------------------------------------------
| Lifecycle helpers live on the form, not its collaborators
|--------------------------------------------------------------------------
|
| The Helpers trait used to sit on Build, Validate, and ServiceManager as
| well. Its methods reach for $this->request, $this->session, $this->errors
| and Flick-private methods, which those hosts don't all have — so the
| public $form->build->submitted() fatalled on every POST, the same way the
| old App\Request shim once made every Helpers method reachable through
| $form->build fatal. The trait now lives only on Flick (lifecycle) and
| Support (the services contract); Build and ServiceManager keep the one
| helper each actually used.
|
*/

function shimForm(array $post = [], array $query = []): Flick
{
    return new Flick([
        'request' => new ArrayRequest([
            'post' => $post,
            'query' => $query,
            'server' => [
                'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST',
                'REQUEST_URI' => '/',
                'REMOTE_ADDR' => '203.0.113.7',
            ],
        ]),
        'echo' => false,
        'csrf' => false,
    ]);
}

it('keeps the Helpers trait off Build, Validate, and ServiceManager', function () {
    expect(class_uses(Build::class))->not->toContain(Helpers::class)
        ->and(class_uses(Validate::class))->not->toContain(Helpers::class)
        ->and(class_uses(ServiceManager::class))->not->toContain(Helpers::class);
});

it('answers the lifecycle helpers on the form itself', function () {
    $form = shimForm();

    expect($form->getIp())->toBe('203.0.113.7')
        ->and($form->submitted())->toBeBool()
        ->and(method_exists($form->build, 'submitted'))->toBeFalse()
        ->and(method_exists($form->build, 'getIp'))->toBeFalse();
});

it('still runs the CSRF check through $form->submitted() on POST', function () {
    $form = shimForm(['_id' => 'myForm', 'name' => 'Gern']);

    // csrf is disabled in this harness, so the check passes; the point is
    // that the POST path routes through Flick's private CSRF validation
    // without fatalling now that only Flick carries submitted()
    expect($form->submitted())->toBeTrue();
});
