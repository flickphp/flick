<?php

declare(strict_types=1);

use Flick\Support\Errors;
use Flick\Support\Support;

/*
 * Audit 2026-08-16, F6-A (a slice of 2026-08-15's B22) — Support pulls in the
 * full Helpers trait, but ~8 of the inherited methods dereference properties
 * only Flick has ($build, $services, $id) or call Flick-private methods
 * (checkForAndValidateCsrfToken()). On a bare Support they used to die with an
 * undefined-method/property Error — or, worse, persistingToSession() silently
 * checked the wrong session key. Each now throws a LogicException naming the
 * real owner, so the failure is immediate and explains itself.
 *
 * Flick's own copies of these methods are untouched: the overrides live only
 * on Support.
 */

beforeEach(function () {
    $this->support = new Support([], new Errors);
});

it('throws a LogicException from submitted() instead of a fatal', function () {
    $this->support->submitted();
})->throws(LogicException::class, 'Flick');

it('throws a LogicException from flushCache() instead of a fatal', function () {
    $this->support->flushCache();
})->throws(LogicException::class, 'Flick');

it('throws a LogicException from errors() instead of a fatal', function () {
    $this->support->errors();
})->throws(LogicException::class, 'Flick');

it('throws a LogicException from each message helper instead of a fatal', function (string $method) {
    $this->support->{$method}('hello');
})->with(['errorMessage', 'infoMessage', 'successMessage', 'warningMessage'])
    ->throws(LogicException::class, 'Flick');

it('throws a LogicException from persistingToSession() instead of silently checking the wrong key', function () {
    $this->support->persistingToSession();
})->throws(LogicException::class, 'Flick');
