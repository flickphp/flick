<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
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

function stepsWithoutText(): array
{
    return [
        'Contact Info' => [
            'fields' => [
                'name' => ['label' => 'Name', 'rules' => ['required']],
                'email' => ['type' => 'email', 'label' => 'Email'],
            ],
        ],
        'Preferences' => [
            'fields' => [
                'newsletter' => ['type' => 'checkbox', 'label' => 'Subscribe to newsletter'],
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// 'text' is optional
// ---------------------------------------------------------------------------

it('renders a step that has no text key', function () {
    $html = $this->form->createMultistep(stepsWithoutText());

    expect($html)->toBeString();
    expect($html)->toContain('Contact Info');
    expect($html)->toContain('name="name"');
});

it('omits the instructions paragraph when a step has no text', function () {
    $html = $this->form->createMultistep(stepsWithoutText());

    expect($html)->not->toContain('<p class="pb-6"></p>');
});

it('still renders the instructions paragraph when text is supplied', function () {
    $steps = stepsWithoutText();
    $steps['Contact Info']['text'] = 'How can we reach you?';

    $html = $this->form->createMultistep($steps);

    expect($html)->toContain('How can we reach you?');
});

it('raises no warnings for a step without text', function () {
    $warnings = [];
    set_error_handler(function ($no, $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    });

    try {
        $this->form->createMultistep(stepsWithoutText());
    } finally {
        restore_error_handler();
    }

    expect($warnings)->toBe([]);
});

// ---------------------------------------------------------------------------
// the review page must survive a field with no stored value
// ---------------------------------------------------------------------------

it('renders the review page when a field has no submitted value', function () {
    $steps = stepsWithoutText();

    // walk both steps, leaving the checkbox unchecked so it stores nothing
    $this->form->createMultistep($steps);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'name' => 'Gern', 'email' => 'gern@example.com'];
    $submitOne = new Flick(['csrf' => false, 'echo' => false]);
    $submitOne->createMultistep($steps, ['testMode' => true]);

    $_POST = ['_id' => 'myForm'];
    $submitTwo = new Flick(['csrf' => false, 'echo' => false]);
    $submitTwo->createMultistep($steps, ['testMode' => true]);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $review = new Flick(['csrf' => false, 'echo' => false]);
    $html = $review->createMultistep($steps);

    expect($html)->toBeString();
    expect($html)->toContain('Gern');
});

it('does not fatal when review data contains a null value', function () {
    // multistepReviewData() returns null for any field name the session never
    // stored; the review table must cope rather than dying in htmlspecialchars()
    $_SESSION['flick']['session'] = true;
    $_SESSION['flick']['_multistep_myForm_steps'] = ['Only Step', 'Review'];
    $_SESSION['flick']['_multistep_myForm_formFields'] = ['present', 'absent'];
    $_SESSION['flick']['present'] = 'a value';
    $_SESSION['flick']['_multistep_myForm_inReview'] = true;
    $_SESSION['flick']['_multistep_myForm_allStepsCompleted'] = true;
    $_SESSION['flick']['_multistep_myForm_completedSteps'] = ['Only Step'];

    $form = new Flick(['csrf' => false, 'echo' => false]);

    expect($form->multistepReviewData())->toHaveKey('absent');
    expect($form->multistepReviewData()['absent'])->toBeNull();

    $html = $form->createMultistep([
        'Only Step' => ['fields' => ['present' => ['label' => 'Present']]],
    ]);

    expect($html)->toBeString();
    expect($html)->toContain('a value');
});
