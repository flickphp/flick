<?php

declare(strict_types=1);

use Flick\Dropdowns\Dropdowns;
use Flick\Support\Support;

/*
|--------------------------------------------------------------------------
| countriesUs is countries with the common three pulled to the top
|--------------------------------------------------------------------------
|
| The two files held the same 239 countries, written out twice. countriesUs is
| now derived with the `+` union, which keeps the left operand's keys, in the
| left operand's order, and appends whatever the right side adds.
|
| array_merge() happens to give the same result today, because the promoted
| labels match their counterparts exactly - the last test here is what keeps
| that true. It is the wrong operator anyway: it takes the right side's value
| for a duplicate key, so the promoted three would silently follow countries.php
| rather than lead it.
|
| These tests pin the outcome, not the mechanism: the promoted three first, a
| separator, then every country the plain list has, with the same labels in the
| same order.
|
*/

beforeEach(function () {
    $this->dropdowns = new Dropdowns(['form' => ['lang' => 'en']], Mockery::mock(Support::class)->makePartial());
});

afterEach(function () {
    Mockery::close();
});

it('opens with the three promoted countries and a separator', function () {
    $countriesUs = $this->dropdowns->load('countriesUs');

    expect(array_slice(array_keys($countriesUs), 0, 4))->toBe(['US', 'CA', 'GB', ' '])
        ->and($countriesUs[' '])->toBe('---------------');
});

it('holds every country the plain list holds, plus the separator', function () {
    $countriesUs = $this->dropdowns->load('countriesUs');
    $countries = $this->dropdowns->load('countries');

    expect(count($countriesUs))->toBe(count($countries) + 1);

    foreach ($countries as $code => $label) {
        expect($countriesUs)->toHaveKey($code)
            ->and($countriesUs[$code])->toBe($label, "label for {$code}");
    }
});

it('keeps the plain list in its own order after the separator', function () {
    $countriesUs = $this->dropdowns->load('countriesUs');
    $countries = $this->dropdowns->load('countries');

    // Everything from the separator on, minus the separator itself: the
    // promoted three are gone from here because the union kept them up top.
    $tail = array_keys($countriesUs);
    $tail = array_slice($tail, array_search(' ', $tail, true) + 1);

    $expected = array_values(array_diff(array_keys($countries), ['US', 'CA', 'GB']));

    expect($tail)->toBe($expected);
});

it('promotes the same labels the plain list uses', function () {
    // A promoted entry that drifted from its counterpart would show one
    // spelling at the top of the menu and another further down.
    $countriesUs = $this->dropdowns->load('countriesUs');
    $countries = $this->dropdowns->load('countries');

    foreach (['US', 'CA', 'GB'] as $code) {
        expect($countriesUs[$code])->toBe($countries[$code], "promoted label for {$code}");
    }
});
