<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Validation\ValidationDelegateInterface;

beforeEach(function () {
    // Reset the static delegate before each test
    Flick::resetDefaultValidationDelegate();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
});

afterEach(function () {
    Flick::resetDefaultValidationDelegate();
    // Clean up POST data to prevent pollution between tests
    $_POST = ['_id' => 'myForm'];
});

it('falls back to invalidRule when no delegate is set', function () {
    $form = new Flick(['csrf' => false]);

    $_POST['email'] = 'test@example.com';
    $form->request('email', ['unknownRule']);

    expect($form->hasError('email'))
        ->toBeTrue()
        ->and($form->getError('email'))
        ->toBe('unknownRule is not a validation rule');
});

/*
 * Audit 2026-08-19, S44. The interface docblock now says so, and this pins it:
 * canHandle() is asked about the bare comma fragments of a rule as well as
 * whole rules, because the rule-string parser uses it to tell a rule token
 * from an argument. A delegate that answers false to a fragment leaves the
 * comma-argument rule in one piece.
 */
it('asks the delegate about bare comma fragments, not only whole rules', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public array $asked = [];

        public function canHandle(string $rule): bool
        {
            $this->asked[] = $rule;

            return false;
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    // Rules given as a string are what the comma splitter sees; an array of
    // rules is taken as already split and never reaches it.
    $_POST['color'] = 'green';
    $form->request('color', 'in:red,green,blue');

    expect($delegate->asked)->toContain('green')
        ->and($delegate->asked)->toContain('blue')
        ->and($form->ok())->toBeTrue();
});

it('delegates unrecognized rules to validation delegate', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public function canHandle(string $rule): bool
        {
            return str_starts_with($rule, 'exists:') || str_starts_with($rule, 'unique:');
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            if ($rule === 'exists:users,id' && $value === '999') {
                return ['The selected '.$field.' does not exist.'];
            }

            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    // Test that delegate receives the rule and returns error
    $_POST['user_id'] = '999';
    $form->request('user_id', ['exists:users,id']);

    expect($form->hasError('user_id'))
        ->toBeTrue()
        ->and($form->getError('user_id'))
        ->toBe('The selected user_id does not exist.');
});

it('does not hijack a delegate rule that shares a native rule prefix', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public array $handledRules = [];

        public function canHandle(string $rule): bool
        {
            return str_starts_with($rule, 'requiredIf:');
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            $this->handledRules[] = $rule;

            return ['The '.$field.' field is required when status is active.'];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    // requiredIf shares the 'required' prefix: prefix matching used to hijack
    // it into the native required rule (which passes on any non-empty value)
    // before the delegate could run.
    $_POST['nickname'] = 'some value';
    $form->request('nickname', ['requiredIf:status,active']);

    expect($delegate->handledRules)->toBe(['requiredIf:status,active'])
        ->and($form->getError('nickname'))->toBe('The nickname field is required when status is active.');
});

it('uses Flick native rules before delegating', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public bool $wasCalled = false;

        public function canHandle(string $rule): bool
        {
            return true; // Handle everything
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            $this->wasCalled = true;

            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    // Email is a native Flick rule, delegate should NOT be called
    $_POST['email'] = 'invalid-email';
    $form->request('email', ['email']);

    expect($form->hasError('email'))
        ->toBeTrue()
        ->and($form->getError('email'))
        ->toContain('valid email');

    // The delegate should not have been invoked for native rules
    expect($delegate->wasCalled)->toBeFalse();
});

it('passes all form data to delegate for cross-field validation', function () {
    $receivedData = null;

    $delegate = new class($receivedData) implements ValidationDelegateInterface
    {
        public function __construct(private &$receivedData) {}

        public function canHandle(string $rule): bool
        {
            return str_starts_with($rule, 'same:');
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            $this->receivedData = $allData;

            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    $_POST['password'] = 'secret123';
    $_POST['password_confirmation'] = 'secret123';
    $_POST['username'] = 'john';
    $form->request('password', ['same:password_confirmation']);

    // The delegate should receive all form data
    expect($receivedData)
        ->toHaveKey('password', 'secret123')
        ->toHaveKey('password_confirmation', 'secret123')
        ->toHaveKey('username', 'john');
});

it('can set and get the default validation delegate', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public function canHandle(string $rule): bool
        {
            return true;
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            return [];
        }
    };

    expect(Flick::getDefaultValidationDelegate())->toBeNull();

    Flick::setDefaultValidationDelegate($delegate);

    expect(Flick::getDefaultValidationDelegate())->toBe($delegate);
});

it('can reset the default validation delegate', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public function canHandle(string $rule): bool
        {
            return true;
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);
    expect(Flick::getDefaultValidationDelegate())->not->toBeNull();

    Flick::resetDefaultValidationDelegate();
    expect(Flick::getDefaultValidationDelegate())->toBeNull();
});

it('takes only the first error when delegate returns multiple', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public function canHandle(string $rule): bool
        {
            return str_starts_with($rule, 'customRule');
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            return [
                'First error message.',
                'Second error message.',
                'Third error message.',
            ];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    $_POST['field'] = 'value';
    $form->request('field', ['customRule']);

    expect($form->hasError('field'))
        ->toBeTrue()
        ->and($form->getError('field'))
        ->toBe('First error message.');
});

it('passes validation when delegate returns empty array', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public function canHandle(string $rule): bool
        {
            return str_starts_with($rule, 'exists:');
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            return []; // Validation passed
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    $_POST['user_id'] = '1';
    $form->request('user_id', ['exists:users,id']);

    expect($form->hasError('user_id'))->toBeFalse();
    expect($form->ok())->toBeTrue();
});

it('falls back to invalidRule when delegate cannot handle the rule', function () {
    $delegate = new class implements ValidationDelegateInterface
    {
        public function canHandle(string $rule): bool
        {
            return str_starts_with($rule, 'exists:'); // Only handles exists:
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $form = new Flick(['csrf' => false]);

    $_POST['field'] = 'value';
    $form->request('field', ['unknownRule']); // Not exists:, so can't handle

    expect($form->hasError('field'))
        ->toBeTrue()
        ->and($form->getError('field'))
        ->toBe('unknownRule is not a validation rule');
});
