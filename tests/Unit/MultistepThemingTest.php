<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = [];
    $_POST = [];
    $_GET = [];
});

afterEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = [];
    $_POST = [];
    $_GET = [];
});

function makeThemedMultistepForm(string $views): Flick
{
    return new Flick([
        'csrf' => false,
        'echo' => false,
        'views' => $views,
    ]);
}

function seedReviewState(Flick $flick): void
{
    $flick->addSessionValue('name', 'John Doe');
    $flick->addSessionValue('email', 'john@example.com');
    $flick->addSessionValue('occupation', 'Developer');
    $flick->addSessionValue('_multistep_myForm_completedSteps', ['Personal Info', 'Professional Info']);
    $flick->addSessionValue('_multistep_myForm_inReview', true);
    $flick->addSessionValue('_multistep_myForm_allStepsCompleted', true);
}

it('renders bulma multistep markup under the bulma theme', function () {
    $flick = makeThemedMultistepForm('bulma');
    seedReviewState($flick);

    $result = $flick->createMultistep(getBasicMultistepForm());

    expect($result)
        ->toContain('title is-3')
        ->toContain('is-striped')
        ->toContain('button is-primary')
        ->not->toContain('btn btn-primary')
        ->not->toContain('table-striped');
});

it('renders bootstrap multistep markup under the bootstrap theme', function () {
    $flick = makeThemedMultistepForm('bootstrap');
    seedReviewState($flick);

    $result = $flick->createMultistep(getBasicMultistepForm());

    expect($result)
        ->toContain('table table-striped')
        ->toContain('btn btn-primary')
        ->not->toContain('title is-3');
});

it('renders the step heading through the theme view', function () {
    $flick = makeThemedMultistepForm('bulma');

    $result = $flick->createMultistep(getBasicMultistepForm());

    expect($result)
        ->toContain('<h3 class="title is-3">')
        ->toContain('Personal Info');
});

it('submitMultistep uses the theme default button classes', function () {
    $flick = makeThemedMultistepForm('bulma');

    expect($flick->submitMultistep())->toContain('button is-primary');

    $bootstrap = makeThemedMultistepForm('bootstrap');

    expect($bootstrap->submitMultistep())->toContain('btn btn-primary');
});

it('submitMultistep still honors custom attributes wholesale', function () {
    $flick = makeThemedMultistepForm('bulma');

    $markup = $flick->submitMultistep('Send', ['class' => 'my-button']);

    expect($markup)
        ->toContain('class="my-button"')
        ->not->toContain('is-primary');
});

it('renders review markup in every theme without leftover placeholders', function (string $views) {
    $flick = makeThemedMultistepForm($views);
    seedReviewState($flick);

    $result = $flick->createMultistep(getBasicMultistepForm());

    expect($result)
        ->toContain('Review Your Information.')
        ->toContain('John Doe')
        ->toContain('?step=submit')
        ->not->toContain('{{');
})->with(['flick', 'bootstrap', 'bootstrap4', 'bulma', 'foundation', 'materialize', 'tailwind']);
