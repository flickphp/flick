<?php

use Flick\Mailer\Message\Message;
use Flick\Mailer\Transport\SmtpTransport;

describe('SmtpTransport::buildEmailData()', function () {

    it('returns plain text format when no HTML', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test Sender',
            to: ['to@example.com'],
            subject: 'Test Subject',
            text: 'Plain text body'
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        expect($result)->toContain('From: "Test Sender" <from@example.com>');
        expect($result)->toContain('To: to@example.com');
        expect($result)->toContain('Subject: Test Subject');
        expect($result)->toContain('Content-Type: text/plain; charset=UTF-8');
        expect($result)->toContain('Plain text body');
        expect($result)->not->toContain('multipart/alternative');
    });

    it('returns multipart/alternative when HTML provided', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Plain text version',
            html: '<h1>HTML version</h1>'
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        // Should contain multipart header
        expect($result)->toContain('Content-Type: multipart/alternative; boundary=');

        // Should contain both text and HTML parts
        expect($result)->toContain('Content-Type: text/plain; charset=UTF-8');
        expect($result)->toContain('Plain text version');
        expect($result)->toContain('Content-Type: text/html; charset=UTF-8');
        expect($result)->toContain('<h1>HTML version</h1>');

        // Should have MIME boundaries
        expect($result)->toMatch('/--[a-f0-9]{32}/');
    });

    it('includes CC header when set', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            cc: ['cc1@example.com', 'cc2@example.com']
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        expect($result)->toContain('Cc: cc1@example.com, cc2@example.com');
    });

    it('includes Reply-To header when set', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            replyTo: 'reply@example.com'
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        expect($result)->toContain('Reply-To: reply@example.com');
    });

    it('includes priority headers when set', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            priority: true
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        // The full three-header set, same as PhpMailTransport: the two used
        // to drift (SMTP lacked X-MSMail-Priority), aligned 2026-08-17.
        expect($result)->toContain('X-Priority: 1');
        expect($result)->toContain('X-MSMail-Priority: High');
        expect($result)->toContain('Importance: High');
    });

    it('includes Date and MIME-Version headers', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        expect($result)->toContain('Date:');
        expect($result)->toContain('MIME-Version: 1.0');
    });

    it('formats multiple recipients correctly', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['user1@example.com', 'user2@example.com', 'user3@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('buildEmailData');

        $result = $method->invoke($transport, $message);

        expect($result)->toContain('To: user1@example.com, user2@example.com, user3@example.com');
    });

});

describe('SmtpTransport::getLastError()', function () {

    it('returns null initially', function () {
        $transport = new SmtpTransport([
            'host' => 'localhost',
            'port' => 587,
        ]);

        expect($transport->getLastError())->toBeNull();
    });

});

describe('SmtpTransport::prepareDataForTransmission()', function () {

    it('dot-stuffs a body line consisting solely of a dot', function () {
        $transport = new SmtpTransport(['host' => 'localhost', 'port' => 587]);

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('prepareDataForTransmission');

        // a lone "." on its own line must be doubled so it is not read as end-of-DATA
        $result = $method->invoke($transport, "Line one\r\n.\r\nLine two");

        expect($result)->toContain("\r\n..\r\n");
    });

    it('normalizes bare LF to CRLF without doubling existing CRLF', function () {
        $transport = new SmtpTransport(['host' => 'localhost', 'port' => 587]);

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('prepareDataForTransmission');

        $result = $method->invoke($transport, "Header: x\r\n\r\nbare\nlf\nlines");

        expect($result)->toBe("Header: x\r\n\r\nbare\r\nlf\r\nlines")
            ->and($result)->not->toContain("\r\r");
    });

    it('dot-stuffs a leading dot at the very start of the data', function () {
        $transport = new SmtpTransport(['host' => 'localhost', 'port' => 587]);

        $reflection = new ReflectionClass($transport);
        $method = $reflection->getMethod('prepareDataForTransmission');

        $result = $method->invoke($transport, '.hidden first line');

        expect($result)->toStartWith('..hidden');
    });

});

describe('SmtpTransport::authenticate()', function () {

    /**
     * Invoke the protected authenticate() with a controlled encrypted flag and
     * an in-memory socket (reads yield no response, so any command fails with
     * an "SMTP error" — distinct from the credentials refusal).
     */
    function flick_invoke_authenticate(array $config, bool $encrypted): ?string
    {
        $transport = new SmtpTransport($config);

        $reflection = new ReflectionClass($transport);
        $encryptedProp = $reflection->getProperty('encrypted');
        $encryptedProp->setValue($transport, $encrypted);
        $socketProp = $reflection->getProperty('socket');
        $socketProp->setValue($transport, fopen('php://memory', 'r+'));

        try {
            $reflection->getMethod('authenticate')->invoke($transport);

            return null;
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
    }

    it('refuses to send credentials over an unencrypted connection', function () {
        $failure = flick_invoke_authenticate([
            'host' => 'localhost',
            'port' => 587,
            'username' => 'user',
            'password' => 'pass',
        ], encrypted: false);

        expect($failure)->toContain('Refusing to send SMTP credentials over an unencrypted connection');
    });

    it('proceeds past the refusal when allowInsecureAuth is set', function () {
        $failure = flick_invoke_authenticate([
            'host' => 'localhost',
            'port' => 587,
            'username' => 'user',
            'password' => 'pass',
            'allowInsecureAuth' => true,
        ], encrypted: false);

        // AUTH LOGIN was attempted (and failed on the dummy socket) instead of
        // being refused outright.
        expect($failure)->not->toContain('Refusing to send SMTP credentials')
            ->and($failure)->toContain('SMTP error');
    });

    it('does not refuse credentials on an encrypted connection', function () {
        $failure = flick_invoke_authenticate([
            'host' => 'localhost',
            'port' => 587,
            'username' => 'user',
            'password' => 'pass',
        ], encrypted: true);

        expect($failure)->not->toContain('Refusing to send SMTP credentials')
            ->and($failure)->toContain('SMTP error');
    });

});
