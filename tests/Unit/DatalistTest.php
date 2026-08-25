<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $this->form = new Flick([
        'echo' => false,
        'csrf' => false,
    ]);
});

it('creates a text input with a datalist using simple array', function () {
    $element = $this->form->text('country', 'Country', '', [
        'datalist' => ['USA', 'Canada', 'Mexico'],
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('list="country-datalist"')
        ->toContain('<datalistid="country-datalist">')
        ->toContain('<optionvalue="USA">')
        ->toContain('<optionvalue="Canada">')
        ->toContain('<optionvalue="Mexico">')
        ->toContain('</datalist>');
});

it('creates a text input with a datalist using key-value pairs', function () {
    $element = $this->form->text('country', 'Country', '', [
        'datalist' => ['us' => 'USA', 'ca' => 'Canada'],
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('list="country-datalist"')
        ->toContain('<optionvalue="us">USA</option>')
        ->toContain('<optionvalue="ca">Canada</option>');
});

it('creates a datalist using the dedicated method', function () {
    $element = $this->form->datalist('browser', 'Browser', '', ['Chrome', 'Firefox', 'Safari']);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('type="text"')
        ->toContain('name="browser"')
        ->toContain('list="browser-datalist"')
        ->toContain('<datalistid="browser-datalist">')
        ->toContain('<optionvalue="Chrome">');
});

it('creates a datalist with a default value', function () {
    $element = $this->form->datalist('browser', 'Browser', 'Chrome', ['Chrome', 'Firefox']);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('value="Chrome"')
        ->toContain('list="browser-datalist"');
});

it('creates a datalist with additional attributes', function () {
    $element = $this->form->datalist('browser', 'Browser', '', ['Chrome', 'Firefox'], [
        'required' => true,
        'class' => 'my-class',
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('required')
        ->toContain('class="flick-inputmy-class"');
});

it('works with search input type', function () {
    $element = $this->form->search('query', 'Search', '', [
        'datalist' => ['popular query 1', 'popular query 2'],
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('type="search"')
        ->toContain('list="query-datalist"')
        ->toContain('<datalistid="query-datalist">');
});

it('works with email input type', function () {
    $element = $this->form->email('email', 'Email', '', [
        'datalist' => ['user@gmail.com', 'user@yahoo.com'],
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('type="email"')
        ->toContain('list="email-datalist"');
});

it('escapes HTML in datalist options', function () {
    $element = $this->form->datalist('test', 'Test', '', ['<script>alert("xss")</script>']);

    expect($element)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert');
});

it('generates unique datalist IDs to avoid conflicts', function () {
    $element1 = $this->form->datalist('field1', 'Field 1', '', ['A', 'B']);
    $element2 = $this->form->datalist('field2', 'Field 2', '', ['C', 'D']);

    expect($element1)->toContain('id="field1-datalist"');
    expect($element2)->toContain('id="field2-datalist"');
});

it('creates a datalist using array configuration', function () {
    $element = $this->form->datalist([
        'name' => 'country',
        'label' => 'Country',
    ], '', '', ['USA', 'Canada']);

    $element = preg_replace('/\s+/', '', $element);

    // When using array config, options are passed as 4th parameter
    expect($element)
        ->toContain('name="country"')
        ->toContain('list="country-datalist"')
        ->toContain('<datalistid="country-datalist">');
});

it('works with url input type', function () {
    $element = $this->form->url('website', 'Website', '', [
        'datalist' => ['https://google.com', 'https://github.com'],
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('type="url"')
        ->toContain('list="website-datalist"')
        ->toContain('<optionvalue="https://google.com">');
});

it('works with tel input type', function () {
    $element = $this->form->tel('phone', 'Phone', '', [
        'datalist' => ['+1-555-0100', '+1-555-0101'],
    ]);

    $element = preg_replace('/\s+/', '', $element);

    expect($element)
        ->toContain('type="tel"')
        ->toContain('list="phone-datalist"');
});
