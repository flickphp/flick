<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * A request value is `mixed`, never guaranteed to be a string. Anywhere core
 * hands one straight to a string-typed method or a string function, a crafted
 * request turns into an uncaught TypeError and a 500.
 *
 * Two entry points were unguarded:
 *   - value()          — POST `email[]=x` to a field a page renders with value()
 *   - ?step=           — GET `?step[]=x` against any multistep form
 *
 * Both must treat a non-string as absent, the way Build::buildFastFormElement()
 * and validateCsrfToken() already do.
 */

describe('value() with an array-shaped request value', function () {
    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_id' => 'myForm'];
        $_GET = [];
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];
    });

    test('returns an empty string instead of crashing on an array POST value', function () {
        $_POST['email'] = ['a@b.com'];

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('email'))->toBe('');
    });

    test('returns an empty string instead of crashing on a nested array POST value', function () {
        $_POST['email'] = [['deep']];

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('email'))->toBe('');
    });

    test('returns an empty string instead of crashing on an array GET value', function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = ['_id' => 'myForm', 'email' => ['a@b.com']];

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('email'))->toBe('');
    });

    test('ignores the developer default when an array was posted', function () {
        $_POST['email'] = ['a@b.com'];

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('email', 'default@example.com'))->toBe('');
    });

    test('still returns a scalar posted value', function () {
        $_POST['email'] = 'a@b.com';

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('email'))->toBe('a@b.com');
    });

    test('still renders an integer posted value', function () {
        $_POST['age'] = 30;

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('age'))->toBe('30');
    });

    test('still renders a float query value', function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = ['_id' => 'myForm', 'price' => 9.5];

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('price'))->toBe('9.5');
    });

    test('clears the field for a boolean posted value', function () {
        $_POST['subscribed'] = true;

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('subscribed'))->toBe('');
    });

    test('still escapes a scalar posted value', function () {
        $_POST['bio'] = '<script>alert(1)</script>';

        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('bio'))->not->toContain('<script>');
    });

    test('still returns the developer default when the field was not submitted', function () {
        $form = new Flick(['csrf' => false, 'echo' => false]);

        expect($form->value('email', 'default@example.com'))->toBe('default@example.com');
    });
});

describe('multistep ?step= with an array-shaped request value', function () {
    beforeEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = [];
        $_POST = [];
        $_GET = [];

        $this->flick = new Flick(['csrf' => false, 'echo' => false]);
    });

    afterEach(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    });

    test('multistepIsComplete() returns false instead of crashing on an array step', function () {
        $_GET['step'] = ['submit'];

        expect($this->flick->multistepIsComplete())->toBeFalse();
    });

    test('multistepIsComplete() stays false for an array step even when every step is complete', function () {
        $_SESSION['flick'] = ['_multistep_myForm_allStepsCompleted' => true];
        $_GET['step'] = ['submit'];

        expect($this->flick->multistepIsComplete())->toBeFalse();
    });

    test('createMultistep() renders the first step instead of crashing on an array step', function () {
        $_GET['step'] = ['Professional Info'];

        $result = $this->flick->createMultistep(getBasicMultistepForm());

        expect($result)->toBeString()
            ->and($result)->toContain('Enter your personal information');
    });

    test('an array step cannot be used to jump ahead', function () {
        $_GET['step'] = ['Review'];

        $form = getBasicMultistepForm();
        $this->flick->createMultistep($form);

        expect($this->flick->multistepCurrentStep($form))->toBe('Personal Info');
    });

    test('a POST submission with an array step still processes the step', function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
        $_GET['step'] = ['Personal Info'];

        $result = $this->flick->createMultistep(getBasicMultistepForm(), ['testMode' => true]);

        expect($result['completedSteps'])->toContain('Personal Info')
            ->and($result['nextStep'])->toBe('Professional Info');
    });

    test('a scalar step still navigates to a reachable step', function () {
        $form = getBasicMultistepForm();

        // complete the first step so the second becomes reachable
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'John Doe', 'email' => 'john@example.com', '_id' => 'myForm'];
        $this->flick->createMultistep($form, ['testMode' => true]);

        // now navigate back to it by name
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET['step'] = 'Personal Info';
        $this->flick->createMultistep($form, ['testMode' => true]);

        expect($this->flick->multistepCurrentStep($form))->toBe('Personal Info');
    });
});
