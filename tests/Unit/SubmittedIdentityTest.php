<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;

/*
|--------------------------------------------------------------------------
| submitted() must identify a form the same way on POST and GET
|--------------------------------------------------------------------------
|
| Both branches ask "did the request name THIS form?", and they answered
| differently. POST scanned every posted key looking for '_id' and compared
| with ==, so PHP's numeric-string juggling applied: a form with id '100'
| accepted a posted _id of '1e2'. GET did a keyed read with ===, which does
| not. Same question, two answers.
|
| The GET branch was right; POST now matches it.
|
| The no-id asymmetry is DELIBERATE and stays: a POST with no configured id is
| a submission (there is nothing else it could be), while a GET with no id only
| counts when the query string carries something - otherwise a visitor arriving
| on ?utm_source=... would see a fully-errored form before typing anything.
|
*/

function identityForm(string $method, array $data, ?string $id = '100'): Flick
{
    $config = [
        'csrf' => false,
        'echo' => false,
        'request' => new ArrayRequest([
            'post' => $method === 'POST' ? $data : [],
            'query' => $method === 'GET' ? $data : [],
            'server' => ['REQUEST_METHOD' => $method],
        ]),
    ];

    if ($id !== null) {
        $config['id'] = $id;
    }

    return new Flick($config);
}

it('agrees between POST and GET for the same id', function (array $data, bool $expected) {
    expect(identityForm('POST', $data)->submitted())->toBe($expected, 'POST')
        ->and(identityForm('GET', $data)->submitted())->toBe($expected, 'GET');
})->with([
    'exact match' => [['_id' => '100'], true],
    'different form' => [['_id' => 'other'], false],
    // '1e2' == '100' under PHP's numeric-string juggling, but it is a
    // different form id and must not match
    'numeric string' => [['_id' => '1e2'], false],
    'leading zeros' => [['_id' => '0100'], false],
    'whitespace' => [['_id' => ' 100'], false],
    'integer-ish' => [['_id' => '100.0'], false],
]);

it('keeps the deliberate no-id asymmetry', function () {
    // Reached by UNSETTING the property, not by config: resolveConfig()
    // defaults 'id' to 'myForm' (Flick.php:1656), so isset($this->id) is
    // always true through the public API and both no-id branches are
    // effectively unreachable. They are kept because the asymmetry they
    // encode is deliberate, and pinned here so a later edit cannot quietly
    // make POST and GET agree in the wrong direction.
    $unsetId = function (Flick $form): Flick {
        (function () {
            unset($this->id);
        })->call($form);

        return $form;
    };

    // POST with no id at all: a submission, full stop
    expect($unsetId(identityForm('POST', ['name' => 'x']))->submitted())->toBeTrue();

    // GET with no id: only when the query carries something
    expect($unsetId(identityForm('GET', ['name' => 'x']))->submitted())->toBeTrue()
        ->and($unsetId(identityForm('GET', []))->submitted())->toBeFalse();
});

it('finds the id wherever it sits in the posted data', function () {
    // the old scan walked every key; a keyed read has to find it just the same
    expect(identityForm('POST', ['a' => '1', 'b' => '2', '_id' => '100'])->submitted())->toBeTrue();
});

it('is false when the request carries no id at all', function () {
    expect(identityForm('POST', ['name' => 'x'])->submitted())->toBeFalse()
        ->and(identityForm('GET', ['name' => 'x'])->submitted())->toBeFalse();
});
