<?php

use Flick\Forms\Forms;
use Flick\Support\Support;

beforeEach(function () {
    $this->config = ['form' => ['lang' => 'en']];
    $this->support = Mockery::mock(Support::class)->makePartial();
});

afterEach(function () {
    Mockery::close();
});

describe('Forms::load()', function () {

    it('loads the login form successfully', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('login');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('action')
            ->and($result)->toHaveKey('method')
            ->and($result)->toHaveKey('fields')
            ->and($result['method'])->toBe('POST')
            ->and($result['fields'])->toHaveKey('username')
            ->and($result['fields'])->toHaveKey('password');
    });

    it('loads the registration form successfully', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('registration');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('fields')
            ->and($result['fields'])->toHaveKey('email');
    });

    it('loads the example form successfully', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('example');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('fields');
    });

});

describe('Forms::loadAsset()', function () {

    it('loads a form using absolute path', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->loadAsset(__DIR__.'/../../lang/en/forms/login.php');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('action')
            ->and($result)->toHaveKey('method')
            ->and($result['attributes']['id'])->toBe('form-login');
    });

});

describe('Form Data Integrity', function () {

    it('login form has required field validation rules', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('login');

        expect($result['fields']['username']['rules'])->toContain('required')
            ->and($result['fields']['password']['rules'])->toContain('required');
    });

    it('login form has custom error messages', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('login');

        expect($result['fields']['username']['messages'])->toHaveKey('required')
            ->and($result['fields']['password']['messages'])->toHaveKey('required');
    });

    it('login form has correct field types', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('login');

        expect($result['fields']['username']['type'])->toBe('text')
            ->and($result['fields']['password']['type'])->toBe('password');
    });

    it('login form has submit button configuration', function () {
        $forms = new Forms($this->config, $this->support);
        $result = $forms->load('login');

        expect($result)->toHaveKey('button')
            ->and($result['button']['text'])->toBe('Login');
    });

});

describe('Configuration', function () {

    it('uses configured language path', function () {
        $config = ['form' => ['lang' => 'en']];
        $forms = new Forms($config, $this->support);
        $result = $forms->load('login');

        expect($result)->toBeArray()
            ->and($result)->not->toBeEmpty();
    });

});
