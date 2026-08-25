<?php

use Flick\Flick;
use Flick\Validation\ValidationDelegateInterface;

/** Records which rules were handed to the delegate, so we can assert on the split. */
class RecordingDelegate implements ValidationDelegateInterface
{
    public static array $seen = [];

    public function canHandle(string $rule): bool
    {
        return str_starts_with($rule, 'exists:');
    }

    public function validate(string $field, mixed $value, string $rule, array $allData = []): array
    {
        self::$seen[] = $rule;

        return [];
    }
}

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm'];
    $_GET = [];

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    Flick::resetDefaultValidationDelegate();
    $_POST = [];
    $_GET = [];
});

// ---------------------------------------------------------------------------
// arguments that contain a colon
// ---------------------------------------------------------------------------

it('accepts a startsWith list of url schemes', function () {
    $_POST['website'] = 'https://example.com';

    $this->form->request('website', 'startsWith:http://,https://');

    expect($this->form->getErrors())->toBe([]);
    expect($this->form->ok())->toBeTrue();
});

it('still rejects a value that matches no startsWith prefix', function () {
    $_POST['website'] = 'ftp://example.com';

    $this->form->request('website', 'startsWith:http://,https://');

    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('website'))->toContain('start with');
});

it('accepts an in list whose values contain colons', function () {
    $_POST['ratio'] = 'b:2';

    $this->form->request('ratio', 'in:a:1,b:2');

    expect($this->form->getErrors())->toBe([]);
    expect($this->form->ok())->toBeTrue();
});

it('rejects a value outside an in list whose values contain colons', function () {
    $_POST['ratio'] = 'c:3';

    $this->form->request('ratio', 'in:a:1,b:2');

    expect($this->form->ok())->toBeFalse();
});

it('gives the same result for the string and array rule forms', function () {
    $_POST['website'] = 'https://example.com';

    $asString = new Flick(['csrf' => false, 'echo' => false]);
    $asString->request('website', 'startsWith:http://,https://');

    $asArray = new Flick(['csrf' => false, 'echo' => false]);
    $asArray->request('website', ['startsWith:http://,https://']);

    expect($asString->getErrors())->toBe($asArray->getErrors());
});

it('never reports an argument as an unknown validation rule', function () {
    $_POST['website'] = 'ftp://example.com';

    // the value fails, so there IS an error - it just has to be the startsWith
    // failure, not "https:// is not a validation rule"
    $this->form->request('website', 'startsWith:http://,https://');

    $errors = implode(' ', $this->form->getErrors());

    expect($errors)->not->toBe('');
    expect($errors)->not->toContain('is not a validation rule');
});

// ---------------------------------------------------------------------------
// a delegated rule after a multi-argument rule must still start a new rule
// ---------------------------------------------------------------------------

it('does not swallow a delegated rule that follows a multi-argument rule', function () {
    RecordingDelegate::$seen = [];
    Flick::setDefaultValidationDelegate(new RecordingDelegate);

    $_POST['role'] = 'admin';

    $form = new Flick(['csrf' => false, 'echo' => false]);
    $form->request('role', 'in:admin,editor,exists:roles,name');

    expect(RecordingDelegate::$seen)->toContain('exists:roles,name');
    expect($form->ok())->toBeTrue();
});

it('keeps treating known rules after a multi-argument rule as separate rules', function () {
    $_POST['color'] = '';

    $this->form->request('color', 'in:red,green,blue,required');

    // `required` is a known rule, so it must not be absorbed into the in list
    expect($this->form->ok())->toBeFalse();
    expect($this->form->getError('color'))->toContain('required');
});
