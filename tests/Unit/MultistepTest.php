<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = [];
    $_POST = [];
    $_GET = [];

    $this->flick = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = [];
    $_POST = [];
    $_GET = [];
});

test('createMultistep initializes the form correctly', function () {
    $form = getBasicMultistepForm();
    $result = $this->flick->createMultistep($form);

    expect($this->flick->multistepCurrentStep($form))->toBe('Personal Info')
        ->and($this->flick->multistepSteps($form))->toBe(['Personal Info', 'Professional Info', 'Review'])
        ->and($result)->toContain('Enter your personal information');
});

test('navigates back to a completed step whose name contains a plus sign', function () {
    $form = [
        'Q+A' => [
            'text' => 'Enter your questions',
            'fields' => [
                'question' => ['type' => 'text', 'label' => 'Question'],
            ],
        ],
        'Details' => [
            'text' => 'Enter the details',
            'fields' => [
                'detail' => ['type' => 'text', 'label' => 'Detail'],
            ],
        ],
    ];

    // complete the first step so it becomes reachable again
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['question' => 'How does it work?', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // navigate back via the breadcrumb link: it emits ?step=Q%2BA, which PHP
    // has already decoded into 'Q+A' by the time it reaches $_GET
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET['step'] = 'Q+A';
    $this->flick->createMultistep($form, ['testMode' => true]);

    expect($this->flick->multistepCurrentStep($form))->toBe('Q+A');
});

test('createMultistep progresses to next step after submission', function () {
    $form = getBasicMultistepForm();

    // submit the first step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $result = $this->flick->createMultistep($form, ['testMode' => true]);  // Enable test mode

    expect($result['nextStep'])->toBe('Professional Info')
        ->and($result['completedSteps'])->toContain('Personal Info')
        ->and($result['sessionData']['name'])->toBe('John Doe')
        ->and($result['sessionData']['email'])->toBe('john@example.com');
});

test('createMultistep allows backward navigation', function () {
    $form = getBasicMultistepForm();

    // submit the first step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // navigate back to the first step
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'Personal Info';
    $result = $this->flick->createMultistep($form, ['testMode' => true]);

    expect($result['nextStep'])->toBe('Personal Info')
        ->and($result['sessionData']['_multistep_myForm_currentStep'])->toBe('Personal Info')
        ->and($result['sessionData']['name'])->toBe('John Doe')
        ->and($result['sessionData']['email'])->toBe('john@example.com');
});

test('createMultistep generates review step correctly', function () {
    $form = getBasicMultistepForm();

    // submit the first step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // submit the second step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['occupation' => 'Developer', '_id' => 'myForm'];
    $result = $this->flick->createMultistep($form, ['testMode' => true]);

    expect($result['nextStep'])->toBe('Review')
        ->and($result['completedSteps'])->toContain('Personal Info')
        ->and($result['completedSteps'])->toContain('Professional Info')
        ->and($result['sessionData']['name'])->toBe('John Doe')
        ->and($result['sessionData']['email'])->toBe('john@example.com')
        ->and($result['sessionData']['occupation'])->toBe('Developer');
});

test('createMultistep handles form completion', function () {
    $form = getBasicMultistepForm();

    // submit the first step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // submit the second step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['occupation' => 'Developer', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // simulate a form submission
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'submit';
    $result = $this->flick->createMultistep($form, ['testMode' => true]);

    // check that the result is null, indicating form completion
    expect($result)->toBeNull()
        ->and($this->flick->multistepIsComplete())->toBeTrue();

    // verify that the form is marked as complete

    // check the final form data
    $formData = $this->flick->multistepFormData();
    expect($formData)->toHaveKeys(['name', 'email', 'occupation'])
        ->and($formData['name'])->toBe('John Doe')
        ->and($formData['email'])->toBe('john@example.com')
        ->and($formData['occupation'])->toBe('Developer')
        ->and($_SESSION)->toBeEmpty();
    // verify that the session has been cleared after retrieving form data
});

test('multistepFormData keeps the session when passed false', function () {
    $form = getBasicMultistepForm();

    // complete the wizard
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['occupation' => 'Developer', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'submit';
    $this->flick->createMultistep($form, ['testMode' => true]);

    // reading without clearing leaves the wizard's session intact
    $formData = $this->flick->multistepFormData(false);
    expect($formData)->toHaveKeys(['name', 'email', 'occupation'])
        ->and($this->flick->multistepIsComplete())->toBeTrue()
        ->and($_SESSION)->not->toBeEmpty();

    // the default call still clears everything
    $formData = $this->flick->multistepFormData();
    expect($formData)->toHaveKeys(['name', 'email', 'occupation'])
        ->and($_SESSION)->toBeEmpty();
});

test('breadcrumb links include the form action path', function () {
    $form = getBasicMultistepForm();

    $flick = new Flick([
        'csrf' => false,
        'echo' => false,
        'action' => '/contact',
    ]);

    // seed a completed step (which renders as a link) with a different current step
    $flick->addSessionValue('_multistep_myForm_completedSteps', ['Personal Info']);
    $flick->addSessionValue('_multistep_myForm_currentStep', 'Professional Info');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $breadcrumbs = $flick->multistepBreadcrumbs($form);

    // the completed-step link must point at the real action, not a bare "?step="
    expect($breadcrumbs)->toContain('/contact?step=');
});

test('multistepBreadcrumbs renders real markup in every view', function (string $views) {
    $form = getBasicMultistepForm();

    $flick = new Flick([
        'csrf' => false,
        'echo' => false,
        'views' => $views,
        'action' => '/contact',
    ]);

    // completed step renders as a link; a different step is current
    $flick->addSessionValue('_multistep_myForm_completedSteps', ['Personal Info']);
    $flick->addSessionValue('_multistep_myForm_currentStep', 'Professional Info');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $breadcrumbs = $flick->multistepBreadcrumbs($form);

    expect($breadcrumbs)->toContain('Personal Info')
        ->toContain('Professional Info')
        ->toContain('/contact?step=')
        ->not->toContain('{{');
})->with(['flick', 'bootstrap', 'bootstrap4', 'bulma', 'foundation', 'materialize', 'tailwind']);

test('createMultistep returns auto-mode breadcrumbs and title in echo=false (H6)', function () {
    $form = getBasicMultistepForm();

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $result = $this->flick->createMultistep($form);

    // the step form was already returned; the H6 gap was the auto-mode title/heading
    expect($result)->toBeString()
        ->toContain('<h3')
        ->toContain('Personal Info')
        ->toContain('<form');
});

test('createMultistep returns the auto-mode review page markup in echo=false (H6)', function () {
    $form = getBasicMultistepForm();

    // seed the session into the completed/review state
    $this->flick->addSessionValue('name', 'John Doe');
    $this->flick->addSessionValue('email', 'john@example.com');
    $this->flick->addSessionValue('occupation', 'Developer');
    $this->flick->addSessionValue('_multistep_myForm_completedSteps', ['Personal Info', 'Professional Info']);
    $this->flick->addSessionValue('_multistep_myForm_inReview', true);
    $this->flick->addSessionValue('_multistep_myForm_allStepsCompleted', true);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $result = $this->flick->createMultistep($form);

    expect($result)->toBeString()
        ->toContain('Review Your Information.')
        ->toContain('John Doe')
        ->toContain('john@example.com')
        ->toContain('?step=submit');
});

test('multistepIsComplete rejects step=submit without completing the steps (H5)', function () {
    $form = getBasicMultistepForm();

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'submit';

    $this->flick->createMultistep($form, ['testMode' => true]);

    expect($this->flick->multistepIsComplete())->toBeFalse();
});

test('createMultistep rejects a jump to an unreached step (H5)', function () {
    $form = getBasicMultistepForm();

    // complete only the first step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // attacker attempts to jump straight to Review
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET['step'] = 'Review';
    $this->flick->createMultistep($form, ['testMode' => true]);

    expect($this->flick->getSessionValue('_multistep_myForm_currentStep'))->not->toBe('Review')
        ->and($this->flick->getSessionValue('_multistep_myForm_allStepsCompleted'))->not->toBe(true);

    // and a subsequent submit must not report the form complete
    $_GET['step'] = 'submit';
    expect($this->flick->multistepIsComplete())->toBeFalse();
});

test('createMultistep step access behavior', function () {
    $form = getBasicMultistepForm();

    // first, let's complete the first step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
    $this->flick->createMultistep($form, ['testMode' => true]);

    // now, try to access the second step
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'Professional Info';
    $result = $this->flick->createMultistep($form, ['testMode' => true]);

    expect($result['nextStep'])->toBe('Professional Info')
        ->and($result['sessionData']['_multistep_myForm_currentStep'])->toBe('Professional Info');

    // trying to skip ahead to the Review step is rejected — Review has not been
    // reached, so the jump is ignored and the current step is left untouched (H5)
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'Review';
    $this->flick->createMultistep($form, ['testMode' => true]);

    expect($this->flick->getSessionValue('_multistep_myForm_currentStep'))->toBe('Professional Info')
        ->and($this->flick->getSessionValue('_multistep_myForm_allStepsCompleted'))->not->toBe(true);

    // completed steps remain navigable
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['step'] = 'Personal Info';
    $result = $this->flick->createMultistep($form, ['testMode' => true]);

    expect($result['nextStep'])->toBe('Personal Info')
        ->and($result['sessionData']['_multistep_myForm_currentStep'])->toBe('Personal Info')
        ->and($result['completedSteps'])->toContain('Personal Info')
        ->and($result['completedSteps'])->not->toContain('Professional Info')
        ->and($result['completedSteps'])->not->toContain('Review');
    // check the completed steps
});

test('two multistep forms with different ids keep separate session state', function () {
    $formDef = getBasicMultistepForm();

    $a = new Flick(['csrf' => false, 'echo' => false, 'id' => 'formA']);
    $b = new Flick(['csrf' => false, 'echo' => false, 'id' => 'formB']);

    $a->createMultistep($formDef, ['testMode' => true]);

    // form A advances to its second step
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'formA'];
    $a->createMultistep($formDef, ['testMode' => true]);

    // form B starts fresh in the same session
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $b->createMultistep($formDef, ['testMode' => true]);

    expect($a->multistepCurrentStep($formDef))->toBe('Professional Info')
        ->and($b->multistepCurrentStep($formDef))->toBe('Personal Info');
});

/*
 * Audit-081701, the deferred D1 finding — revisited now because the deferral
 * said "only in a deliberate 1.0 public-API pass", and this is one.
 *
 * multistepCurrentStep() returned htmlspecialchars() of the stored step name,
 * while multistepSteps() returns the raw $form keys. All seven breadcrumb views
 * compare the two:
 *
 *     if ($this->multistepCurrentStep($form) == $step)
 *
 * so for a step named with &, <, >, " or ' the comparison never matched and the
 * active crumb was never highlighted. The views already escape $step at output -
 * three times each - so the escaping in the model was both redundant for display
 * and the cause of the bug. Escaping belongs to the renderer, which is the
 * settled position from 2026-08-11.
 */

test('the current step keeps its raw name so breadcrumb views can match it', function () {
    $form = [
        'Billing & Shipping' => [
            'text' => 'Where does it go?',
            'fields' => ['address' => ['type' => 'text', 'label' => 'Address']],
        ],
        'Payment' => [
            'text' => 'How are you paying?',
            'fields' => ['card' => ['type' => 'text', 'label' => 'Card']],
        ],
    ];

    $this->flick->createMultistep($form);

    expect($this->flick->multistepCurrentStep($form))->toBe('Billing & Shipping')
        ->and($this->flick->multistepSteps($form))->toContain('Billing & Shipping');
});

test('the active breadcrumb is highlighted for a step name needing escaping', function (string $stepName) {
    $form = [
        $stepName => [
            'text' => 'Where does it go?',
            'fields' => ['address' => ['type' => 'text', 'label' => 'Address']],
        ],
        'Payment' => [
            'text' => 'How are you paying?',
            'fields' => ['card' => ['type' => 'text', 'label' => 'Card']],
        ],
    ];

    $this->flick->createMultistep($form);

    // The comparison the views actually make.
    expect($this->flick->multistepCurrentStep($form))->toBe($stepName);

    // And the rendered breadcrumbs mark it active while still escaping it.
    $html = $this->flick->multistepBreadcrumbs($form);

    expect($html)->toContain('active')
        ->and($html)->toContain(htmlspecialchars($stepName, ENT_QUOTES, 'UTF-8'))
        ->and($html)->not->toContain('<script');
})->with([
    'ampersand' => ['Billing & Shipping'],
    'angle brackets' => ['Size <10'],
    'double quote' => ['The "Big" Step'],
    'single quote' => ["Tim's Step"],
]);
