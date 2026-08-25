<?php

use Flick\Mailer\Message\FormDataFormatter;

describe('FormDataFormatter::toText()', function () {

    it('formats simple key-value pairs', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        expect($result)->toContain('Name: John');
        expect($result)->toContain('Email: john@example.com');
    });

    it('converts snake_case to Title Case', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        expect($result)->toContain('First Name: John');
        expect($result)->toContain('Last Name: Doe');
    });

    it('converts camelCase to Title Case', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]);

        expect($result)->toContain('First Name: John');
        expect($result)->toContain('Last Name: Doe');
    });

    it('excludes specified keys', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'name' => 'John',
            'password' => 'secret',
        ], ['password']);

        expect($result)->toContain('Name: John');
        expect($result)->not->toContain('password');
        expect($result)->not->toContain('secret');
    });

    it('excludes keys starting with underscore', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'name' => 'John',
            '_token' => 'abc123',
            '_session' => 'xyz',
        ]);

        expect($result)->toContain('Name: John');
        expect($result)->not->toContain('_token');
        expect($result)->not->toContain('abc123');
    });

    it('formats array values as comma-separated', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'colors' => ['red', 'green', 'blue'],
        ]);

        expect($result)->toContain('Colors: red, green, blue');
    });

    it('formats boolean values as Yes/No', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toText([
            'subscribed' => true,
            'terms' => false,
        ]);

        expect($result)->toContain('Subscribed: Yes');
        expect($result)->toContain('Terms: No');
    });

});

describe('FormDataFormatter::toHtml()', function () {

    it('wraps output in table', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toHtml([
            'name' => 'John',
        ]);

        expect($result)->toStartWith('<table');
        expect($result)->toEndWith('</table>');
    });

    it('creates table rows for each field', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toHtml([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        expect($result)->toContain('<tr>');
        expect($result)->toContain('Name');
        expect($result)->toContain('John');
        expect($result)->toContain('Email');
    });

    it('escapes html in values', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toHtml([
            'message' => '<script>alert("xss")</script>',
        ]);

        expect($result)->toContain('&lt;script&gt;');
        expect($result)->not->toContain('<script>');
    });

    it('converts newlines to br tags', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toHtml([
            'message' => "Line 1\nLine 2",
        ]);

        expect($result)->toContain('Line 1<br />');
    });

    it('excludes specified keys', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toHtml([
            'name' => 'John',
            'password' => 'secret',
        ], ['password']);

        expect($result)->toContain('Name');
        expect($result)->not->toContain('password');
    });

    it('excludes keys starting with underscore', function () {
        $formatter = new FormDataFormatter;

        $result = $formatter->toHtml([
            'name' => 'John',
            '_token' => 'abc123',
        ]);

        expect($result)->toContain('Name');
        expect($result)->not->toContain('_token');
    });

});
