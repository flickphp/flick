<?php

use Flick\App\Validate;
use Flick\Flick;

beforeEach(function () {
    // Ensure no validation delegate is set (clean state between tests)
    Flick::resetDefaultValidationDelegate();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm'; // Add form ID for submission validation
    $this->form = new Flick([
        'csrf' => false,
    ]);
});

it('validates accepted', function () {
    // not accepted (empty)
    $_POST['one'] = '';
    $this->form->request('one', ['accepted'], ['accepted' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // not accepted (invalid value)
    $_POST['two'] = 'no';
    $this->form->request('two', ['accepted'], ['accepted' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // accepted with "yes"
    $_POST['three'] = 'yes';
    $this->form->request('three', ['accepted']);
    expect($this->form->hasError('three'))->toBeFalse();

    // accepted with "on"
    $_POST['four'] = 'on';
    $this->form->request('four', ['accepted']);
    expect($this->form->hasError('four'))->toBeFalse();

    // accepted with "1"
    $_POST['five'] = '1';
    $this->form->request('five', ['accepted']);
    expect($this->form->hasError('five'))->toBeFalse();

    // accepted with "true"
    $_POST['six'] = 'true';
    $this->form->request('six', ['accepted']);
    expect($this->form->hasError('six'))->toBeFalse();
});

it('validates alpha', function () {
    // a digit is not alpha (the old ' ' case now trims to empty pre-validation,
    // and empty input skips the rule)
    $_POST['one'] = 'John3';
    $this->form->request('one', ['alpha'], ['alpha' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = 'John';
    $this->form->request('two', ['alpha']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates alphaDash', function () {
    $_POST['one'] = '$';
    $this->form->request('one', ['alphaDash'], ['alphaDash' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = 'John123';
    $this->form->request('two', ['alphaDash']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates alphaNumeric', function () {
    // letters + digits pass
    $_POST['ok'] = 'John123';
    $this->form->request('ok', ['alphaNumeric']);
    expect($this->form->hasError('ok'))->toBeFalse();

    // a space fails (unlike alpha, alphaNumeric allows no spaces)
    $_POST['space'] = 'John 123';
    $this->form->request('space', ['alphaNumeric'], ['alphaNumeric' => 'ERROR']);
    expect($this->form->hasError('space'))->toBeTrue()
        ->and($this->form->getError('space'))->toBe('ERROR');

    // a symbol fails
    $_POST['sym'] = 'John-123';
    $this->form->request('sym', ['alphaNumeric']);
    expect($this->form->hasError('sym'))->toBeTrue();
});

it('validates digits', function () {
    // exactly N digits passes (leading zeros allowed)
    $_POST['zip'] = '01234';
    $this->form->request('zip', ['digits:5']);
    expect($this->form->hasError('zip'))->toBeFalse();

    // wrong length fails
    $_POST['short'] = '1234';
    $this->form->request('short', ['digits:5'], ['digits' => 'ERROR']);
    expect($this->form->hasError('short'))->toBeTrue()
        ->and($this->form->getError('short'))->toBe('ERROR');

    // non-digits fail
    $_POST['nan'] = '12a45';
    $this->form->request('nan', ['digits:5']);
    expect($this->form->hasError('nan'))->toBeTrue();
});

it('validates digitsBetween', function () {
    // length within range passes
    $_POST['ok'] = '12345';
    $this->form->request('ok', ['digitsBetween:4,6']);
    expect($this->form->hasError('ok'))->toBeFalse();

    // too short fails
    $_POST['short'] = '123';
    $this->form->request('short', ['digitsBetween:4,6'], ['digitsBetween' => 'ERROR']);
    expect($this->form->hasError('short'))->toBeTrue()
        ->and($this->form->getError('short'))->toBe('ERROR');

    // non-digits fail
    $_POST['nan'] = '12ab';
    $this->form->request('nan', ['digitsBetween:1,9']);
    expect($this->form->hasError('nan'))->toBeTrue();
});

it('validates startsWith', function () {
    // starts with one of the prefixes → pass
    $_POST['ok'] = 'https://example.com';
    $this->form->request('ok', ['startsWith:http,https']);
    expect($this->form->hasError('ok'))->toBeFalse();

    // no matching prefix → fail
    $_POST['bad'] = 'ftp://example.com';
    $this->form->request('bad', ['startsWith:http,https'], ['startsWith' => 'ERROR']);
    expect($this->form->hasError('bad'))->toBeTrue()
        ->and($this->form->getError('bad'))->toBe('ERROR');
});

it('validates endsWith', function () {
    // ends with one of the suffixes → pass
    $_POST['ok'] = 'user@example.com';
    $this->form->request('ok', ['endsWith:.com,.net']);
    expect($this->form->hasError('ok'))->toBeFalse();

    // no matching suffix → fail
    $_POST['bad'] = 'user@example.org';
    $this->form->request('bad', ['endsWith:.com,.net'], ['endsWith' => 'ERROR']);
    expect($this->form->hasError('bad'))->toBeTrue()
        ->and($this->form->getError('bad'))->toBe('ERROR');
});

it('keeps comma params attached for startsWith/endsWith/digitsBetween in string-form rules', function () {
    // The comma inside startsWith:http,https must not be split into separate rules.
    $_POST['u'] = 'ftp://x';
    $this->form->request('u', 'required, startsWith:http,https');
    expect($this->form->hasError('u'))->toBeTrue();

    $_POST['ok'] = 'https://x';
    $this->form->request('ok', 'required, startsWith:http,https');
    expect($this->form->hasError('ok'))->toBeFalse();

    $_POST['d'] = '123';
    $this->form->request('d', 'required, digitsBetween:4,6');
    expect($this->form->hasError('d'))->toBeTrue();
});

it('validates between', function () {
    // input outside range → error
    $_POST['one'] = '5';
    $this->form->request('one', ['between:1,3'], ['between' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // input inside range → no error
    $_POST['two'] = '2';
    $this->form->request('two', ['between:1,3']);
    expect($this->form->hasError('two'))->toBeFalse();

    // input at lower boundary → no error (inclusive)
    $_POST['three'] = '1';
    $this->form->request('three', ['between:1,3']);
    expect($this->form->hasError('three'))->toBeFalse();

    // input at upper boundary → no error (inclusive)
    $_POST['four'] = '3';
    $this->form->request('four', ['between:1,3']);
    expect($this->form->hasError('four'))->toBeFalse();
});

it('validates boolean', function () {
    // invalid boolean value
    $_POST['one'] = 'maybe';
    $this->form->request('one', ['boolean'], ['boolean' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // valid "true"
    $_POST['two'] = 'true';
    $this->form->request('two', ['boolean']);
    expect($this->form->hasError('two'))->toBeFalse();

    // valid "false"
    $_POST['three'] = 'false';
    $this->form->request('three', ['boolean']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid "1"
    $_POST['four'] = '1';
    $this->form->request('four', ['boolean']);
    expect($this->form->hasError('four'))->toBeFalse();

    // valid "0"
    $_POST['five'] = '0';
    $this->form->request('five', ['boolean']);
    expect($this->form->hasError('five'))->toBeFalse();

    // empty should be skipped
    $_POST['six'] = '';
    $this->form->request('six', ['boolean']);
    expect($this->form->hasError('six'))->toBeFalse();
});

it('validates confirmed', function () {
    // no confirmation field
    $_POST['password'] = 'secret123';
    $this->form->request('password', ['confirmed'], ['confirmed' => 'ERROR']);
    expect($this->form->hasError('password'))
        ->toBeTrue()
        ->and($this->form->getError('password'))
        ->toBe('ERROR');

    // confirmation does not match
    $_POST['password2'] = 'secret123';
    $_POST['password2_confirmation'] = 'different';
    $this->form->request('password2', ['confirmed'], ['confirmed' => 'ERROR']);
    expect($this->form->hasError('password2'))->toBeTrue();

    // valid confirmation
    $_POST['password3'] = 'secret123';
    $_POST['password3_confirmation'] = 'secret123';
    $this->form->request('password3', ['confirmed']);
    expect($this->form->hasError('password3'))->toBeFalse();
});

it('validates creditCard', function () {
    // invalid card number
    $_POST['one'] = '1234567890123456';
    $this->form->request('one', ['creditCard'], ['creditCard' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // too short
    $_POST['two'] = '123';
    $this->form->request('two', ['creditCard'], ['creditCard' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // valid Visa test card (passes Luhn)
    $_POST['three'] = '4111111111111111';
    $this->form->request('three', ['creditCard']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid with spaces
    $_POST['four'] = '4111 1111 1111 1111';
    $this->form->request('four', ['creditCard']);
    expect($this->form->hasError('four'))->toBeFalse();

    // valid with dashes
    $_POST['five'] = '4111-1111-1111-1111';
    $this->form->request('five', ['creditCard']);
    expect($this->form->hasError('five'))->toBeFalse();
});

it('validates date', function () {
    // invalid date fails with the date message, not invalidRule
    $_POST['one'] = 'not-a-date';
    $this->form->request('one', ['date'], ['date' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // the rule exists server-side: no spurious "not a validation rule"
    $_POST['two'] = 'nope';
    $this->form->request('two', ['date']);
    expect($this->form->getError('two'))->not->toContain('not a validation rule');

    // valid date in the configured format (default Y-m-d) passes
    $_POST['three'] = '2026-07-06';
    $this->form->request('three', ['date']);
    expect($this->form->hasError('three'))->toBeFalse();

    // impossible date fails
    $_POST['four'] = '2026-02-30';
    $this->form->request('four', ['date']);
    expect($this->form->hasError('four'))->toBeTrue();
});

it('validates dateAfter', function () {
    $_POST['one'] = '2021-01-01';
    $this->form->request('one', ['after:2024-01-01'], ['after' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '2025-01-01';
    $this->form->request('two', ['after:2024-01-01']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates dateAfterOrEqual', function () {
    $_POST['one'] = '2021-01-01';
    $this->form->request('one', ['afterOrEqual:2024-01-01'], ['afterOrEqual' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '2024-01-01';
    $this->form->request('two', ['afterOrEqual:2024-01-01']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '2024-01-02';
    $this->form->request('three', ['afterOrEqual:2024-01-01']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates dateBefore', function () {
    $_POST['one'] = '2025-01-01';
    $this->form->request('one', ['before:2024-01-01'], ['before' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '2023-01-01';
    $this->form->request('two', ['before:2024-01-01']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates dateBeforeOrEqual', function () {
    $_POST['one'] = '2025-01-01';
    $this->form->request('one', ['beforeOrEqual:2024-01-01'], ['beforeOrEqual' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '2023-01-01';
    $this->form->request('two', ['beforeOrEqual:2024-01-01']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '2024-01-01';
    $this->form->request('three', ['beforeOrEqual:2024-01-01']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates email', function () {
    $_POST['one'] = 'one.com';
    $this->form->request('one', ['email'], ['email' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = 'two@email.com';
    $this->form->request('two', ['email']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates equals', function () {
    $_POST['one'] = '123456';
    $this->form->request('one', ['equals:5'], ['equals' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '5';
    $this->form->request('two', ['equals:5']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates exact', function () {
    $_POST['one'] = '123456';
    $this->form->request('one', ['exact:5'], ['exact' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '12345';
    $this->form->request('two', ['exact:5']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates greaterThan', function () {
    // input less than threshold → error
    $_POST['one'] = '1';
    $this->form->request('one', ['greaterThan:7'], ['greaterThan' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // input equal to threshold → error (must be strictly greater)
    $_POST['two'] = '7';
    $this->form->request('two', ['greaterThan:7']);
    expect($this->form->hasError('two'))->toBeTrue();

    // input greater than threshold → no error
    $_POST['three'] = '8';
    $this->form->request('three', ['greaterThan:7']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates greaterThanOrEqual', function () {
    $_POST['one'] = '1';
    $this->form->request('one', ['greaterThanOrEqual:5'], ['greaterThanOrEqual' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '5';
    $this->form->request('two', ['greaterThanOrEqual:5']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '6';
    $this->form->request('three', ['greaterThanOrEqual:5']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates in', function () {
    // value not in list
    $_POST['one'] = 'yellow';
    $this->form->request('one', ['in:red,green,blue'], ['in' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // value in list
    $_POST['two'] = 'red';
    $this->form->request('two', ['in:red,green,blue']);
    expect($this->form->hasError('two'))->toBeFalse();

    // value in list (middle)
    $_POST['three'] = 'green';
    $this->form->request('three', ['in:red,green,blue']);
    expect($this->form->hasError('three'))->toBeFalse();

    // empty should be skipped
    $_POST['four'] = '';
    $this->form->request('four', ['in:red,green,blue']);
    expect($this->form->hasError('four'))->toBeFalse();
});

it('validates integer', function () {
    $_POST['one'] = 'a';
    $this->form->request('one', ['int'], ['int' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '5';
    $this->form->request('two', ['int']);
    expect($this->form->hasError('two'))->toBeFalse();

    // "0" is a valid integer — filter_var("0", FILTER_VALIDATE_INT) returns 0 (falsy)
    // so the check must use === false, not just truthiness
    $_POST['three'] = '0';
    $this->form->request('three', ['int']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('fires a custom message keyed integer for the integer rule', function () {
    $_POST['age'] = 'abc';
    $this->form->request('age', ['integer'], ['integer' => 'Age must be a whole number']);
    expect($this->form->hasError('age'))
        ->toBeTrue()
        ->and($this->form->getError('age'))
        ->toBe('Age must be a whole number');

    // the 'int' alias must keep firing a message keyed 'int'
    $_POST['count'] = 'abc';
    $this->form->request('count', ['int'], ['int' => 'Count must be a whole number']);
    expect($this->form->hasError('count'))
        ->toBeTrue()
        ->and($this->form->getError('count'))
        ->toBe('Count must be a whole number');
});

// Audit-081702 P14-A. The alias map used to be private, so the client side kept
// no copy of it and shipped 'int'/'r' to the browser as literal rule names. No
// JS validator answers to either and an unknown rule is treated as a pass, so
// the documented aliases were valid client-side and rejected on submit.
it('resolves a rule alias to the handler it dispatches to', function () {
    expect(Validate::canonicalRuleName('int'))->toBe('integer')
        ->and(Validate::canonicalRuleName('r'))->toBe('required');
});

it('leaves a rule name that is already canonical alone', function () {
    foreach (Validate::getRuleNames() as $name) {
        expect(Validate::canonicalRuleName($name))->toBe($name);
    }
});

it('passes an unmapped rule name straight through', function () {
    // Delegated and custom rules are not in the map; renaming one would break a
    // framework adapter's own rules on the way to the client.
    expect(Validate::canonicalRuleName('requiredIf'))->toBe('requiredIf')
        ->and(Validate::canonicalRuleName('somethingCustom'))->toBe('somethingCustom')
        ->and(Validate::canonicalRuleName(''))->toBe('');
});

it('matches rule names case-sensitively, like the dispatcher does', function () {
    expect(Validate::canonicalRuleName('INT'))->toBe('INT')
        ->and(Validate::canonicalRuleName('R'))->toBe('R');
});

it('validates lessThan', function () {
    // input greater than threshold → error
    $_POST['one'] = '6';
    $this->form->request('one', ['lessThan:5'], ['lessThan' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // input equal to threshold → error (must be strictly less)
    $_POST['two'] = '5';
    $this->form->request('two', ['lessThan:5']);
    expect($this->form->hasError('two'))->toBeTrue();

    // input less than threshold → no error
    $_POST['three'] = '4';
    $this->form->request('three', ['lessThan:5']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates lessThanOrEqual', function () {
    $_POST['one'] = '6';
    $this->form->request('one', ['lessThanOrEqual:5'], ['lessThanOrEqual' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '4';
    $this->form->request('two', ['lessThanOrEqual:5']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '5';
    $this->form->request('three', ['lessThanOrEqual:5']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates matches', function () {
    $_POST['one'] = 'one';
    $_POST['email1'] = 'two';
    $this->form->request('one', ['matches:email1'], ['matches' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = 'one';
    $_POST['email2'] = 'one';
    $this->form->request('two', ['matches:email2']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates max', function () {
    $_POST['one'] = '123456';
    $this->form->request('one', ['max:5'], ['max' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '1';
    $this->form->request('two', ['max:5']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates min', function () {
    $_POST['one'] = '123';
    $this->form->request('one', ['min:5'], ['min' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '12345';
    $this->form->request('two', ['min:5']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates notIn', function () {
    // value in forbidden list
    $_POST['one'] = 'admin';
    $this->form->request('one', ['notIn:admin,root,superuser'], ['notIn' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // value in forbidden list (middle)
    $_POST['two'] = 'root';
    $this->form->request('two', ['notIn:admin,root,superuser'], ['notIn' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // value not in forbidden list
    $_POST['three'] = 'john';
    $this->form->request('three', ['notIn:admin,root,superuser']);
    expect($this->form->hasError('three'))->toBeFalse();

    // empty should be skipped
    $_POST['four'] = '';
    $this->form->request('four', ['notIn:admin,root,superuser']);
    expect($this->form->hasError('four'))->toBeFalse();
});

it('validates notMatches', function () {
    $_POST['one'] = 'one';
    $_POST['email1'] = 'one';
    $this->form->request('one', ['notMatches:email1'], ['notMatches' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = 'one';
    $_POST['email2'] = 'two';
    $this->form->request('two', ['notMatches:email2']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates notRegex', function () {
    // input matches pattern → error (must NOT match)
    $_POST['one'] = '1';
    $this->form->request('one', ['notRegex:/^[0-9]+$/'], ['notRegex' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // input does NOT match pattern → no error
    $_POST['two'] = 'a';
    $this->form->request('two', ['notRegex:/^[0-9]+$/']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates numeric', function () {
    $_POST['one'] = 'John';
    $this->form->request('one', ['numeric'], ['numeric' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '123';
    $this->form->request('two', ['numeric']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates phone', function () {
    // too short
    $_POST['one'] = '12345';
    $this->form->request('one', ['phone'], ['phone' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // invalid characters
    $_POST['two'] = 'abc1234567';
    $this->form->request('two', ['phone'], ['phone' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // valid 10 digit
    $_POST['three'] = '1234567890';
    $this->form->request('three', ['phone']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid with formatting
    $_POST['four'] = '(123) 456-7890';
    $this->form->request('four', ['phone']);
    expect($this->form->hasError('four'))->toBeFalse();

    // valid international
    $_POST['five'] = '+1 234 567 8901';
    $this->form->request('five', ['phone']);
    expect($this->form->hasError('five'))->toBeFalse();
});

it('validates regex', function () {
    // input does NOT match pattern → error
    $_POST['one'] = 'a';
    $this->form->request('one', ['regex:/^[0-9]+$/'], ['regex' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // input matches pattern → no error
    $_POST['two'] = '1';
    $this->form->request('two', ['regex:/^[0-9]+$/']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates required', function () {
    $_POST['one'] = '';
    $this->form->request('one', ['required'], ['required' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '1';
    $this->form->request('two', ['required']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('fires a custom message keyed r for the required rule', function () {
    $_POST['name'] = '';
    $this->form->request('name', ['required'], ['required' => 'Name is required']);
    expect($this->form->hasError('name'))
        ->toBeTrue()
        ->and($this->form->getError('name'))
        ->toBe('Name is required');

    // the 'r' alias must keep firing a message keyed 'r'
    $_POST['nickname'] = '';
    $this->form->request('nickname', ['r'], ['r' => 'Nickname is required']);
    expect($this->form->hasError('nickname'))
        ->toBeTrue()
        ->and($this->form->getError('nickname'))
        ->toBe('Nickname is required');
});

it('treats a whitespace-only value as failing required (parity with client-side)', function () {
    $_POST['spaces'] = '   ';
    $this->form->request('spaces', ['required'], ['required' => 'ERROR']);
    expect($this->form->hasError('spaces'))->toBeTrue();

    // '0' still satisfies required (not treated as empty)
    $_POST['zero'] = '0';
    $this->form->request('zero', ['required']);
    expect($this->form->hasError('zero'))->toBeFalse();
});

it('validates requiredWith', function () {
    $_POST['email'] = 'email@domain.com';

    $_POST['one'] = '';
    $this->form->request('one', ['requiredWith:email'], ['requiredWith' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '1';
    $this->form->request('two', ['requiredWith:email']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates strongPassword', function () {
    // too short (default 8 chars)
    $_POST['one'] = 'Aa1!';
    $this->form->request('one', ['strongPassword'], ['strongPassword' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // missing uppercase
    $_POST['two'] = 'abcd1234!';
    $this->form->request('two', ['strongPassword'], ['strongPassword' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // missing lowercase
    $_POST['three'] = 'ABCD1234!';
    $this->form->request('three', ['strongPassword'], ['strongPassword' => 'ERROR']);
    expect($this->form->hasError('three'))->toBeTrue();

    // missing number
    $_POST['four'] = 'Abcdefgh!';
    $this->form->request('four', ['strongPassword'], ['strongPassword' => 'ERROR']);
    expect($this->form->hasError('four'))->toBeTrue();

    // missing special character
    $_POST['five'] = 'Abcdefg1';
    $this->form->request('five', ['strongPassword'], ['strongPassword' => 'ERROR']);
    expect($this->form->hasError('five'))->toBeTrue();

    // valid password (default 8 chars)
    $_POST['six'] = 'Abcdefg1!';
    $this->form->request('six', ['strongPassword']);
    expect($this->form->hasError('six'))->toBeFalse();

    // custom length: too short for 12 chars
    $_POST['seven'] = 'Abcdefg1!';
    $this->form->request('seven', ['strongPassword:12'], ['strongPassword' => 'ERROR']);
    expect($this->form->hasError('seven'))->toBeTrue();

    // custom length: valid 12 char password
    $_POST['eight'] = 'Abcdefghij1!';
    $this->form->request('eight', ['strongPassword:12']);
    expect($this->form->hasError('eight'))->toBeFalse();
});

it('validates validIp', function () {
    $_POST['one'] = '11111';
    $this->form->request('one', ['ip'], ['ip' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = '127.0.0.1';
    $this->form->request('two', ['ip']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates ipv4', function () {
    // invalid IPv4 (IPv6 address)
    $_POST['one'] = '::1';
    $this->form->request('one', ['ipv4'], ['ipv4' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // invalid IPv4 (random string)
    $_POST['two'] = 'not-an-ip';
    $this->form->request('two', ['ipv4'], ['ipv4' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // valid IPv4
    $_POST['three'] = '192.168.1.1';
    $this->form->request('three', ['ipv4']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid IPv4 localhost
    $_POST['four'] = '127.0.0.1';
    $this->form->request('four', ['ipv4']);
    expect($this->form->hasError('four'))->toBeFalse();
});

it('validates ipv6', function () {
    // invalid IPv6 (IPv4 address)
    $_POST['one'] = '192.168.1.1';
    $this->form->request('one', ['ipv6'], ['ipv6' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // invalid IPv6 (random string)
    $_POST['two'] = 'not-an-ip';
    $this->form->request('two', ['ipv6'], ['ipv6' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // valid IPv6 localhost
    $_POST['three'] = '::1';
    $this->form->request('three', ['ipv6']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid IPv6 full
    $_POST['four'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
    $this->form->request('four', ['ipv6']);
    expect($this->form->hasError('four'))->toBeFalse();
});

it('validates json', function () {
    // invalid JSON
    $_POST['one'] = 'not json';
    $this->form->request('one', ['json'], ['json' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // invalid JSON (malformed)
    $_POST['two'] = '{foo: bar}';
    $this->form->request('two', ['json'], ['json' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // valid JSON object
    $_POST['three'] = '{"foo": "bar"}';
    $this->form->request('three', ['json']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid JSON array
    $_POST['four'] = '[1, 2, 3]';
    $this->form->request('four', ['json']);
    expect($this->form->hasError('four'))->toBeFalse();

    // valid JSON string
    $_POST['five'] = '"hello"';
    $this->form->request('five', ['json']);
    expect($this->form->hasError('five'))->toBeFalse();

    // empty should be skipped
    $_POST['six'] = '';
    $this->form->request('six', ['json']);
    expect($this->form->hasError('six'))->toBeFalse();
});

it('validates validUrl', function () {
    $_POST['one'] = '11111.domain';
    $this->form->request('one', ['url'], ['url' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    $_POST['two'] = 'https://www.domain.com';
    $this->form->request('two', ['url']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates uuid', function () {
    // invalid UUID (random string)
    $_POST['one'] = 'not-a-uuid';
    $this->form->request('one', ['uuid'], ['uuid' => 'ERROR']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');

    // invalid UUID (wrong version)
    $_POST['two'] = '550e8400-e29b-11d4-a716-446655440000';
    $this->form->request('two', ['uuid'], ['uuid' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();

    // valid UUID v4
    $_POST['three'] = '550e8400-e29b-41d4-a716-446655440000';
    $this->form->request('three', ['uuid']);
    expect($this->form->hasError('three'))->toBeFalse();

    // valid UUID v4 (lowercase)
    $_POST['four'] = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    $this->form->request('four', ['uuid']);
    expect($this->form->hasError('four'))->toBeFalse();

    // valid UUID v4 (uppercase)
    $_POST['five'] = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';
    $this->form->request('five', ['uuid']);
    expect($this->form->hasError('five'))->toBeFalse();

    // empty should be skipped
    $_POST['six'] = '';
    $this->form->request('six', ['uuid']);
    expect($this->form->hasError('six'))->toBeFalse();
});

it('validates a string of rules', function () {
    $_POST['one'] = '111111';
    $this->form->request('one', 'min:3,max:5');
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('one can not be more than 5 characters');
});

it('validates a form string with multiple rule violations', function () {
    $_POST['one'] = '111111';
    $this->form->request('one[min:3,max:5]');
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('one can not be more than 5 characters');
});

it('checks for an invalid rule', function () {
    $_POST['one'] = '111111';
    $this->form->request('one', ['foo']);
    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('foo is not a validation rule');
});

// Regression tests for numeric comparison with negatives, floats, multi-digit numbers
// (Previously used string comparison which broke for multi-digit: '9' > '10' lexicographically)

it('validates greaterThan with negative numbers', function () {
    $_POST['one'] = '-5';
    $this->form->request('one', ['greaterThan:-10']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '-10';
    $this->form->request('two', ['greaterThan:-5'], ['greaterThan' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();
});

it('validates greaterThan with floats', function () {
    $_POST['one'] = '3.5';
    $this->form->request('one', ['greaterThan:3.0']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '3.0';
    $this->form->request('two', ['greaterThan:3.5'], ['greaterThan' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();
});

it('validates greaterThan with multi-digit numbers', function () {
    // This was the string comparison bug: '9' > '10' is true lexicographically
    $_POST['one'] = '10';
    $this->form->request('one', ['greaterThan:9']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '100';
    $this->form->request('two', ['greaterThan:99']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates greaterThan rejects non-numeric input', function () {
    $_POST['one'] = 'abc';
    $this->form->request('one', ['greaterThan:5']);
    expect($this->form->hasError('one'))->toBeTrue();
});

it('validates greaterThanOrEqual with negatives and floats', function () {
    $_POST['one'] = '-5';
    $this->form->request('one', ['greaterThanOrEqual:-5']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '3.5';
    $this->form->request('two', ['greaterThanOrEqual:3.5']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '10';
    $this->form->request('three', ['greaterThanOrEqual:9']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates lessThan with multi-digit numbers', function () {
    // String comparison bug: '9' < '10' is false lexicographically
    $_POST['one'] = '9';
    $this->form->request('one', ['lessThan:10']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '99';
    $this->form->request('two', ['lessThan:100']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates lessThan with negatives and floats', function () {
    $_POST['one'] = '-10';
    $this->form->request('one', ['lessThan:-5']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '2.5';
    $this->form->request('two', ['lessThan:3.0']);
    expect($this->form->hasError('two'))->toBeFalse();
});

it('validates lessThan rejects non-numeric input', function () {
    $_POST['one'] = 'abc';
    $this->form->request('one', ['lessThan:5']);
    expect($this->form->hasError('one'))->toBeTrue();
});

it('validates lessThanOrEqual with negatives and multi-digit', function () {
    $_POST['one'] = '-5';
    $this->form->request('one', ['lessThanOrEqual:-5']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '9';
    $this->form->request('two', ['lessThanOrEqual:10']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '10';
    $this->form->request('three', ['lessThanOrEqual:10']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates between with multi-digit and negative ranges', function () {
    $_POST['one'] = '9';
    $this->form->request('one', ['between:1,10']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '10';
    $this->form->request('two', ['between:1,10']);
    expect($this->form->hasError('two'))->toBeFalse();

    $_POST['three'] = '-5';
    $this->form->request('three', ['between:-10,0']);
    expect($this->form->hasError('three'))->toBeFalse();

    $_POST['four'] = '3.5';
    $this->form->request('four', ['between:1.0,5.0']);
    expect($this->form->hasError('four'))->toBeFalse();
});

// Regression tests for mb_strlen in min/max/exact
// (Previously used strlen which counts bytes, not characters)

it('validates exact with multibyte characters', function () {
    // 'café' is 4 characters but 5 bytes in UTF-8
    $_POST['one'] = 'café';
    $this->form->request('one', ['exact:4']);
    expect($this->form->hasError('one'))->toBeFalse();

    // '日本語' is 3 characters but 9 bytes in UTF-8
    $_POST['two'] = '日本語';
    $this->form->request('two', ['exact:3']);
    expect($this->form->hasError('two'))->toBeFalse();

    // Emoji: 'Hi😀' is 3 characters but 6 bytes
    $_POST['three'] = 'Hi😀';
    $this->form->request('three', ['exact:3']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates min with multibyte characters', function () {
    // '日本語' is 3 characters
    $_POST['one'] = '日本語';
    $this->form->request('one', ['min:3']);
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '日本語';
    $this->form->request('two', ['min:4'], ['min' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();
});

it('validates max with multibyte characters', function () {
    // '日本語' is 3 characters
    $_POST['one'] = '日本語';
    $this->form->request('one', ['max:2'], ['max' => 'ERROR']);
    expect($this->form->hasError('one'))->toBeTrue();

    $_POST['two'] = '日本語';
    $this->form->request('two', ['max:3']);
    expect($this->form->hasError('two'))->toBeFalse();

    // 'café' is 4 characters
    $_POST['three'] = 'café';
    $this->form->request('three', ['max:4']);
    expect($this->form->hasError('three'))->toBeFalse();
});

it('validates between with string-form rules', function () {
    // documented form: $form->request('age', 'between:18,65')
    $_POST['one'] = '25';
    $this->form->request('one', 'between:18,65');
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '70';
    $this->form->request('two', 'between:18,65', ['between' => 'ERROR']);
    expect($this->form->hasError('two'))
        ->toBeTrue()
        ->and($this->form->getError('two'))
        ->toBe('ERROR');
});

it('validates in with string-form rules', function () {
    // documented form: $form->request('color', 'in:red,green,blue')
    $_POST['one'] = 'green';
    $this->form->request('one', 'in:red,green,blue');
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = 'purple';
    $this->form->request('two', 'in:red,green,blue', ['in' => 'ERROR']);
    expect($this->form->hasError('two'))
        ->toBeTrue()
        ->and($this->form->getError('two'))
        ->toBe('ERROR');
});

it('validates notIn with string-form rules', function () {
    // documented form: $form->request('username', 'notIn:admin,root,superuser')
    $_POST['one'] = 'timmy';
    $this->form->request('one', 'notIn:admin,root,superuser');
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = 'admin';
    $this->form->request('two', 'notIn:admin,root,superuser', ['notIn' => 'ERROR']);
    expect($this->form->hasError('two'))
        ->toBeTrue()
        ->and($this->form->getError('two'))
        ->toBe('ERROR');
});

it('validates string-form multi-value rules combined with other rules', function () {
    $_POST['one'] = '25';
    $this->form->request('one', 'required, between:18,65');
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = '';
    $this->form->request('two', 'required, between:18,65', ['required' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();
});

it('validates multi-value rules inside bracket string form definitions', function () {
    $_POST['age'] = '25';
    $_POST['color'] = 'green';
    $this->form->request('age[between:18,65], color[in:red,green,blue]');
    expect($this->form->hasError('age'))->toBeFalse()
        ->and($this->form->hasError('color'))->toBeFalse();

    $_POST['age'] = '70';
    $_POST['color'] = 'purple';
    $this->form->request('age[between:18,65], color[in:red,green,blue]');
    expect($this->form->hasError('age'))->toBeTrue()
        ->and($this->form->hasError('color'))->toBeTrue();
});

it('validates string-form regex rules containing commas', function () {
    // a regex quantifier contains a comma: {2,4}
    $_POST['one'] = 'abc';
    $this->form->request('one', 'regex:/^[a-z]{2,4}$/');
    expect($this->form->hasError('one'))->toBeFalse();

    $_POST['two'] = 'abcdef';
    $this->form->request('two', 'regex:/^[a-z]{2,4}$/', ['regex' => 'ERROR']);
    expect($this->form->hasError('two'))->toBeTrue();
});

it('reports a clear missingArgument error for a malformed between rule (M4)', function () {
    $_POST['one'] = '5';
    $this->form->request('one', ['between:5'], ['missingArgument' => 'ERROR']);

    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');
});

it('accepts a well-formed between rule after the malformed-argument guard (M4)', function () {
    $_POST['ok'] = '5';
    $this->form->request('ok', ['between:1,10']);

    expect($this->form->hasError('ok'))->toBeFalse();
});

it('rejects digits in an alpha field (M5)', function () {
    $_POST['one'] = 'John123';
    $this->form->request('one', ['alpha'], ['alpha' => 'ERROR']);

    expect($this->form->hasError('one'))
        ->toBeTrue()
        ->and($this->form->getError('one'))
        ->toBe('ERROR');
});

it('accepts letters and spaces in an alpha field (M5)', function () {
    $_POST['two'] = 'Mary Jane';
    $this->form->request('two', ['alpha']);

    expect($this->form->hasError('two'))->toBeFalse();
});

it('counts strongPassword length in characters, not bytes (L3)', function () {
    // 'Pä1!wxy' is 7 characters but 8 bytes; it must fail the default min length of 8
    $_POST['pw'] = 'Pä1!wxy';
    $this->form->request('pw', ['strongPassword'], ['strongPassword' => 'ERROR']);

    expect($this->form->hasError('pw'))->toBeTrue();

    // the same password padded to 8 real characters passes
    $_POST['pw2'] = 'Pä1!wxyz';
    $this->form->request('pw2', ['strongPassword']);

    expect($this->form->hasError('pw2'))->toBeFalse();
});
