<?php

declare(strict_types=1);

use Flick\Flick;

/*
|--------------------------------------------------------------------------
| A stored step that the live form no longer has
|--------------------------------------------------------------------------
|
| Audit 2026-08-19, S02. Three readers derived "the current step" from the
| session independently - createMultistep(), multistepCurrentStep() and
| multistepStepIsReachable() - and none reconciled the stored name against
| the form definition. Rename a step while a visitor's session still holds
| the old name and the next request rendered the stale heading, warned on
| the undefined key, then threw a TypeError out of create(). One private
| resolver now answers all three and falls back to the first step whenever
| the stored name is absent from the definition.
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

function staleStepForm(): Flick
{
    return new Flick(['csrf' => false, 'echo' => false, 'id' => 'stale']);
}

/** The definition after a rename: 'Old Step' has become 'Account'. */
function renamedDefinition(): array
{
    return [
        'Account' => [
            'fields' => ['name' => ['type' => 'text', 'label' => 'Name']],
        ],
        'Profile' => [
            'fields' => ['bio' => ['type' => 'text', 'label' => 'Bio']],
        ],
    ];
}

it('renders the first step instead of fatalling when the stored step no longer exists', function () {
    $form = staleStepForm();
    $form->addSessionValue('_multistep_stale_currentStep', 'Old Step');

    $html = $form->createMultistep(renamedDefinition());

    expect($html)->toContain('name="name"')
        ->and($html)->not->toContain('Old Step')
        ->and($form->getSessionValue('_multistep_stale_currentStep'))->toBe('Account');
});

it('reports the first step from multistepCurrentStep() when the stored step no longer exists', function () {
    $form = staleStepForm();
    $form->addSessionValue('_multistep_stale_currentStep', 'Old Step');

    expect($form->multistepCurrentStep(renamedDefinition()))->toBe('Account');
});

it('still honours a stored step that the form does have', function () {
    $form = staleStepForm();
    $form->addSessionValue('_multistep_stale_completedSteps', ['Account']);
    $form->addSessionValue('_multistep_stale_currentStep', 'Profile');

    $html = $form->createMultistep(renamedDefinition());

    expect($html)->toContain('name="bio"')
        ->and($form->multistepCurrentStep(renamedDefinition()))->toBe('Profile');
});

it('treats a stale stored step as the first step when deciding what ?step= may reach', function () {
    // With 'Old Step' stored and nothing completed, the first step is the one
    // reachable target; the second is not, so asking for it stays on the first.
    $form = staleStepForm();
    $form->addSessionValue('_multistep_stale_currentStep', 'Old Step');
    $_GET = ['step' => 'Profile'];

    $html = $form->createMultistep(renamedDefinition());

    expect($html)->toContain('name="name"')
        ->and($html)->not->toContain('name="bio"');
});
