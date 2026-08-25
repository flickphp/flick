<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $this->form = new Flick([
        'csrf' => false,
    ]);
});

it('displays an error message', function () {
    ob_start();
    $this->form->errorMessage('this is the message');
    $element = ob_get_contents();
    ob_end_clean();

    expect($element)->toBeString()->toContain('this is the message');
});

it('displays a success message', function () {
    ob_start();
    $this->form->successMessage('this is the message');
    $element = ob_get_contents();
    ob_end_clean();

    expect($element)->toBeString()->toContain('this is the message');
});

it('displays a warning message', function () {
    ob_start();
    $this->form->warningMessage('this is the message');
    $element = ob_get_contents();
    ob_end_clean();

    expect($element)->toBeString()->toContain('this is the message');
});

it('displays an info message', function () {
    ob_start();
    $this->form->infoMessage('this is the message');
    $element = ob_get_contents();
    ob_end_clean();

    expect($element)->toBeString()->toContain('this is the message');
});

it('retrieves a value from the config array', function () {
    expect($this->form->config('id'))
        ->toEqual('myForm');
});

it('checks if the input is not empty', function () {
    expect($this->form->inputIsNotEmpty([]))
        ->toBeFalse()
        ->and($this->form->inputIsNotEmpty(['foo']))
        ->toBeTrue();
});

it('returns the human readable error messages bag', function () {
    $this->form->addError('name', 'name is required');

    expect($this->form->getError('name'))
        ->toContain('name is required');
});

it('gets IP address', function () {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    expect($this->form->getIp())->toBe('127.0.0.1');

    $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
    expect($this->form->getIp())->toBe('10.0.0.1');

    // Client-IP is client-forgeable and is ignored; X-Forwarded-For wins
    $_SERVER['HTTP_CLIENT_IP'] = '192.168.0.1';
    expect($this->form->getIp())->toBe('10.0.0.1');

    unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
});

it('slug strings', function () {
    expect($this->form->slug('Hello World!'))->toBe('Hello_World')
        ->and($this->form->slug('Test-123_ABC'))->toBe('Test_123_ABC');
});

it('transliterates accented letters when slugging instead of dropping them (M12)', function () {
    expect($this->form->slug('José'))->toBe('Jose')
        ->and($this->form->slug('Spécial Chàracters'))->toBe('Special_Characters')
        ->and($this->form->slug('café'))->toBe('cafe');
});

it('keeps non-Latin scripts as a Unicode slug instead of returning empty (M12)', function () {
    // Cyrillic/CJK have no ASCII transliteration; a Unicode-aware fallback keeps
    // the letters rather than reducing the value to an empty string.
    expect($this->form->slug('Привет'))->not->toBe('')
        ->and($this->form->slug('Привет мир'))->toBe('Привет_мир')
        ->and($this->form->slug('日本語'))->toBe('日本語');
});

it('verifies passwords', function () {
    $password = 'myPassword123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    expect($this->form->verifyPassword($password, $hashedPassword))->toBeTrue()
        ->and($this->form->verifyPassword('wrongPassword', $hashedPassword))->toBeFalse();
});

it('clears POST and GET data after submission', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['test'] = 'value';
    $_POST['_id'] = 'myForm'; // Add form ID to match default
    $_GET['query'] = 'search';

    // Debug: check what's happening step by step
    expect($this->form->config('id'))->toBe('myForm');
    expect(isset($this->form->id))->toBeTrue();
    expect($this->form->id)->toBe('myForm');

    // Check POST data
    expect($_POST['_id'])->toBe('myForm');

    // Manually check the submitted() logic
    expect($_SERVER['REQUEST_METHOD'])->toBe('POST');

    // Check CSRF function result (should be true since csrf is disabled)
    $reflection = new ReflectionMethod(get_class($this->form), 'checkForAndValidateCsrfToken');
    $csrfResult = $reflection->invoke($this->form);
    expect($csrfResult)->toBeTrue();

    // Now test submitted()
    expect($this->form->submitted())->toBeTrue();
    expect($this->form->ok())->toBeTrue();

    $this->form->clear();

    expect($_POST)->toBeEmpty()
        ->and($_GET)->toBeEmpty();
});

it('dumps without dying', function () {
    ob_start();
    $this->form->dump('test');
    $output = ob_get_clean();

    expect($output)->toContain('<pre>')
        ->and($output)->toContain('test')
        ->and($output)->toContain('</pre>');
});
