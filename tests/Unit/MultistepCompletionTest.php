<?php

use Flick\Flick;

/*
|--------------------------------------------------------------------------
| Deriving multistep completion
|--------------------------------------------------------------------------
|
| "Every step is done" was STORED as its own session flag, written in two
| places and read in four. A flag can disagree with the list of steps it is
| supposed to summarise - and a session carrying the flag but no completed
| steps let ?step=submit through, which is the whole gate on skipping ahead.
|
| It is now derived from the completed-step list, so the two cannot disagree.
| The empty-list guard is the load-bearing line: array_diff([], $x) === [] is
| vacuously true, so without it a session with NO steps recorded would read as
| fully complete.
|
*/

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

function completionForm(): Flick
{
    return new Flick(['csrf' => false, 'echo' => false]);
}

it('does not treat a seeded completion flag as a completed run', function () {
    $_GET['step'] = 'submit';

    $form = completionForm();
    // the legacy flag on its own, with no steps and no completed steps
    $form->addSessionValue('_multistep_myForm_allStepsCompleted', true);

    expect($form->multistepIsComplete())->toBeFalse();
});

it('does not treat an empty completed-step list as complete', function () {
    $_GET['step'] = 'submit';

    $form = completionForm();
    $form->addSessionValue('_multistep_myForm_steps', ['Personal Info', 'Review']);
    $form->addSessionValue('_multistep_myForm_completedSteps', []);

    // array_diff([], $x) === [] is vacuously true; without the empty-list
    // guard this reads as "every step is done"
    expect($form->multistepIsComplete())->toBeFalse();
});

it('treats a genuinely finished run as complete', function () {
    $_GET['step'] = 'submit';

    $form = completionForm();
    $form->addSessionValue('_multistep_myForm_steps', ['Personal Info', 'Professional Info', 'Review']);
    $form->addSessionValue('_multistep_myForm_completedSteps', ['Personal Info', 'Professional Info']);

    expect($form->multistepIsComplete())->toBeTrue();
});

it('is not complete while a data step is still outstanding', function () {
    $_GET['step'] = 'submit';

    $form = completionForm();
    $form->addSessionValue('_multistep_myForm_steps', ['Personal Info', 'Professional Info', 'Review']);
    $form->addSessionValue('_multistep_myForm_completedSteps', ['Personal Info']);

    expect($form->multistepIsComplete())->toBeFalse();
});

it('renders a first step literally named Review on the first two requests', function () {
    // 'Review' is also the synthetic final step's name. A form whose OWN first
    // step is called Review must still render that step's fields, and must not
    // be treated as finished.
    $form = completionForm();

    $definition = [
        'Review' => [
            'text' => 'Check your details',
            'fields' => ['name' => ['type' => 'text', 'label' => 'Name']],
        ],
        'Payment' => [
            'text' => 'Pay up',
            'fields' => ['card' => ['type' => 'text', 'label' => 'Card']],
        ],
    ];

    $first = $form->createMultistep($definition);
    expect($first)->toContain('name="name"');

    $second = $form->createMultistep($definition);
    expect($second)->toContain('name="name"');

    // and submit is still refused
    $_GET['step'] = 'submit';
    expect($form->multistepIsComplete())->toBeFalse();
});

it('migrates a pre-1.0 session that stored the lists as JSON strings', function () {
    $form = completionForm();
    $form->addSessionValue('_multistep_myForm_steps', '["Personal Info","Review"]');
    $form->addSessionValue('_multistep_myForm_completedSteps', '["Personal Info"]');

    expect($form->multistepSteps())->toBe(['Personal Info', 'Review'])
        ->and($form->multistepCompletedSteps())->toContain('Personal Info');
});

it('treats a corrupt list payload as an empty list, not a TypeError', function () {
    $form = completionForm();
    $form->addSessionValue('_multistep_myForm_steps', '{not json');
    $form->addSessionValue('_multistep_myForm_completedSteps', 42);

    expect($form->multistepSteps())->toBe([])
        ->and($form->multistepCompletedSteps())->toBe([]);
});
