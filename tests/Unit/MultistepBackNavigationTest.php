<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Multistep back-navigation from the review step
|--------------------------------------------------------------------------
|
| The guide promises this outright (guide/multistep-forms.md:158):
|
|   "Users see all their submitted data in a table and can navigate back to
|    edit any step via breadcrumbs."
|
| Clicking one of those breadcrumbs while on Review used to return HTTP 500.
| createMultistep() read $currentStep from the session ('Review'), then wrote
| the requested step back to the session without updating that local, and
| carried on: the "am I on Review?" guard reads the session and no longer
| fired, so the step lookup ran with the stale 'Review' name. Review is a
| synthetic step appended to the step list, never a key in the caller's form
| array, so the lookup produced null and create() threw a TypeError.
|
| The redirect() on the requested-step branch does not save it. It only acts
| when the form was submitted and validated; a breadcrumb click is a GET, so
| it returns null and execution falls through.
|
*/

const BACK_NAV_FORM = [
    'Personal Info' => [
        'fields' => [
            'name' => ['type' => 'text', 'label' => 'Name'],
        ],
    ],
    'Professional Info' => [
        'fields' => [
            'occupation' => ['type' => 'text', 'label' => 'Occupation'],
        ],
    ],
];

/**
 * A wizard sitting on the synthetic Review step with every real step complete,
 * receiving a GET for $requestedStep - exactly what a breadcrumb click sends.
 */
function wizardOnReview(?string $requestedStep, string $id = 'backnav'): Flick
{
    $get = $requestedStep === null ? [] : ['step' => $requestedStep];
    $uri = '/signup'.($requestedStep === null ? '' : '?step='.urlencode($requestedStep));

    $form = new Flick([
        'request' => new ArrayRequest([
            'server' => ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => 'GET'],
            'get' => $get,
        ]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
        'id' => $id,
    ]);

    $form->addSessionValue("_multistep_{$id}_completedSteps", ['Personal Info', 'Professional Info']);
    $form->addSessionValue("_multistep_{$id}_currentStep", 'Professional Info');
    $form->addSessionValue("_multistep_{$id}_inReview", true);

    return $form;
}

it('renders the requested step when a breadcrumb is clicked from review', function () {
    $form = wizardOnReview('Personal Info');

    $html = $form->createMultistep(BACK_NAV_FORM);

    expect($html)->toBeString()
        ->and($html)->toContain('name="name"')
        ->and($html)->not->toContain('{{');
});

it('can go back to any completed step, not just the first', function () {
    $form = wizardOnReview('Professional Info');

    $html = $form->createMultistep(BACK_NAV_FORM);

    expect($html)->toContain('name="occupation"');
});

it('moves the session onto the step the user went back to', function () {
    $form = wizardOnReview('Personal Info');

    $form->createMultistep(BACK_NAV_FORM);

    expect($form->getSessionValue('_multistep_backnav_currentStep'))->toBe('Personal Info');
});

it('still shows the review page when no step is requested', function () {
    $form = wizardOnReview(null);

    $html = $form->createMultistep(BACK_NAV_FORM);

    // The review page renders its own markup and no step field element.
    expect($html)->toBeString()
        ->and($html)->not->toContain('name="name"');
});

it('ignores a step that is not reachable and stays on review', function () {
    $form = wizardOnReview('Nonexistent Step');

    $html = $form->createMultistep(BACK_NAV_FORM);

    expect($html)->toBeString()
        ->and($form->multistepIsInReview())->toBeTrue();
});
