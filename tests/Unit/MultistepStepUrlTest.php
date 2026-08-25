<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Multistep step urls
|--------------------------------------------------------------------------
|
| Breadcrumb links and the review-step submit link both point at the current
| url with a ?step= target appended. The templates used to glue that on with
| plain string concatenation, which produced a second '?' whenever the page
| already carried a query string:
|
|   /signup?ref=abc  ->  /signup?ref=abc/?step=Personal+Info
|
| PHP parses that as ref="abc/?step=Personal Info" and never sets $_GET['step'],
| so back-navigation and the final submit silently did nothing. The url has to
| be composed: keep whatever query string is already there, append step with
| '&' when needed, and never introduce a stray path separator.
|
*/

const STEP_FORM = [
    'Personal Info' => [
        'fields' => [
            'name' => ['type' => 'text', 'label' => 'Name'],
        ],
    ],
    'Professional Info' => [
        'fields' => [
            'occupation' => ['type' => 'text', 'label' => 'Occupation'],
        ],
    ],
];

const THEMES = ['flick', 'bootstrap', 'bootstrap4', 'bulma', 'foundation', 'materialize', 'tailwind'];

function multistepForm(string $requestUri, string $theme = 'flick', string $id = 'wizard'): Flick
{
    $form = new Flick([
        'request' => new ArrayRequest(['server' => ['REQUEST_URI' => $requestUri]]),
        'session' => new ArraySession,
        'views' => $theme,
        'echo' => false,
        'csrf' => false,
        'id' => $id,
    ]);

    // Mark the first step complete so its breadcrumb renders as a link, and sit
    // the wizard on the second step so the first is not the "current" one.
    $form->addSessionValue("_multistep_{$id}_completedSteps", ['Personal Info']);
    $form->addSessionValue("_multistep_{$id}_currentStep", 'Professional Info');

    return $form;
}

/** Pull every href out of a fragment of markup. */
function hrefsIn(string $html): array
{
    preg_match_all('/href="([^"]*)"/', $html, $matches);

    return array_map(html_entity_decode(...), $matches[1]);
}

/** Resolve a relative href against a host so the query string can be parsed. */
function queryOf(string $href): array
{
    $query = parse_url('https://example.test'.$href, PHP_URL_QUERY) ?? '';
    parse_str($query, $params);

    return $params;
}

it('keeps an existing query string on breadcrumb links', function (string $theme) {
    $form = multistepForm('/signup?ref=abc&utm=x', $theme);

    $hrefs = hrefsIn($form->multistepBreadcrumbs(STEP_FORM));

    expect($hrefs)->not->toBeEmpty();

    foreach ($hrefs as $href) {
        expect(substr_count($href, '?'))->toBe(1, "more than one '?' in {$href}");

        $params = queryOf($href);

        expect($params)->toHaveKey('step')
            ->and($params['step'])->toBe('Personal Info')
            ->and($params)->toHaveKey('ref')
            ->and($params['ref'])->toBe('abc')
            ->and($params)->toHaveKey('utm')
            ->and($params['utm'])->toBe('x');
    }
})->with(THEMES);

it('keeps an existing query string on the review submit link', function (string $theme) {
    $form = multistepForm('/signup?ref=abc', $theme);

    $hrefs = hrefsIn($form->submitMultistep('Submit Form'));

    expect($hrefs)->not->toBeEmpty();

    foreach ($hrefs as $href) {
        expect(substr_count($href, '?'))->toBe(1, "more than one '?' in {$href}");

        $params = queryOf($href);

        expect($params)->toHaveKey('step')
            ->and($params['step'])->toBe('submit')
            ->and($params)->toHaveKey('ref')
            ->and($params['ref'])->toBe('abc');
    }
})->with(THEMES);

it('builds a clean step url when the page has no query string', function (string $theme) {
    $form = multistepForm('/signup', $theme);

    foreach (hrefsIn($form->multistepBreadcrumbs(STEP_FORM)) as $href) {
        expect($href)->toBe('/signup?step=Personal+Info');
    }

    foreach (hrefsIn($form->submitMultistep('Submit Form')) as $href) {
        expect($href)->toBe('/signup?step=submit');
    }
})->with(THEMES);

it('replaces a step already in the url rather than adding a second one', function (string $theme) {
    // Support::requestPath() drops the query string for the form action because
    // createMultistep() stalls on the current step while a ?step= is present.
    // The same hazard applies here: a breadcrumb built on a page that already
    // carries ?step= must target its own step, not inherit the current one.
    $form = multistepForm('/signup?ref=abc&step=Professional+Info', $theme);

    foreach (hrefsIn($form->multistepBreadcrumbs(STEP_FORM)) as $href) {
        $params = queryOf($href);

        expect($params['step'])->toBe('Personal Info')
            ->and($params['ref'])->toBe('abc')
            ->and(substr_count($href, 'step='))->toBe(1, "duplicate step in {$href}");
    }
})->with(THEMES);

it('does not put a path separator before the query string', function (string $theme) {
    $form = multistepForm('/signup', $theme);

    $html = $form->multistepBreadcrumbs(STEP_FORM).$form->submitMultistep('Submit Form');

    foreach (hrefsIn($html) as $href) {
        expect($href)->not->toContain('/?step=');
    }
})->with(THEMES);
