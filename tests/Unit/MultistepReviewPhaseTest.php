<?php

use Flick\Flick;

/*
|--------------------------------------------------------------------------
| The review phase is a flag, not a step named 'Review'
|--------------------------------------------------------------------------
|
| One string used to be the current data step, the synthetic review phase,
| and the ?step= token all at once. A developer is free to name a DATA step
| 'Review'; once every step completed, clicking that step's breadcrumb
| could only ever reopen the review screen — the data step's fields were
| unreachable. The phase now lives in its own session flag; 'Review' in the
| public getters is presentation only.
|
*/

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = [];
    $_POST = [];
    $_GET = [];
});

afterEach(function () {
    $_SESSION = [];
    $_POST = [];
    $_GET = [];
});

function reviewPhaseForm(): Flick
{
    return new Flick(['csrf' => false, 'echo' => false]);
}

/** A form whose FIRST data step is legitimately named 'Review'. */
function formWithReviewDataStep(): array
{
    return [
        'Review' => [
            'fields' => ['terms' => ['type' => 'text', 'label' => 'Terms']],
        ],
        'Details' => [
            'fields' => ['name' => ['type' => 'text', 'label' => 'Name']],
        ],
    ];
}

function completeBothSteps(array $form): void
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['terms' => 'agreed', '_id' => 'myForm'];
    reviewPhaseForm()->createMultistep($form, ['testMode' => true]);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'Gern', '_id' => 'myForm'];
    reviewPhaseForm()->createMultistep($form, ['testMode' => true]);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
}

it('reopens a data step named Review from its breadcrumb after completion', function () {
    $form = formWithReviewDataStep();
    completeBothSteps($form);

    $_GET = ['step' => 'Review'];
    $html = reviewPhaseForm()->createMultistep($form);

    expect($html)->toContain('name="terms"')
        ->and($html)->not->toContain('Review Your Information.');
});

it('still shows the review screen without a step target after completion', function () {
    $form = formWithReviewDataStep();
    completeBothSteps($form);

    $html = reviewPhaseForm()->createMultistep($form);

    expect($html)->toContain('Review Your Information.');
});
