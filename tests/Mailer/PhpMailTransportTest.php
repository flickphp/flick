<?php

use Flick\Mailer\Message\Message;
use Flick\Mailer\Transport\MultipartBody;
use Flick\Mailer\Transport\PhpMailTransport;

/*
 * Audit 2026-08-15, A13 — the multipart body is assembled once, by
 * MultipartBody, and the boundary is a per-send local threaded through the
 * calls. PhpMailTransport used to keep it in a mutable instance field coupled
 * to the buildBody-then-buildHeaders call order (with a manual reset), and
 * carried its own copy of the body-assembly block beside SmtpTransport's.
 */

function phpMailMessage(array $overrides = []): Message
{
    return new Message(...array_merge([
        'fromAddress' => 'from@example.com',
        'fromName' => 'Test',
        'to' => ['to@example.com'],
        'subject' => 'Subject',
        'text' => 'Plain text body',
    ], $overrides));
}

describe('MultipartBody', function () {

    it('builds the multipart/alternative body both transports emit', function () {
        $message = phpMailMessage(['text' => 'Plain text', 'html' => '<h1>HTML content</h1>']);
        $boundary = MultipartBody::generateBoundary();

        $result = MultipartBody::build($message, $boundary);

        expect($result)->toContain('Content-Type: text/plain; charset=UTF-8')
            ->and($result)->toContain('Plain text')
            ->and($result)->toContain('Content-Type: text/html; charset=UTF-8')
            ->and($result)->toContain('<h1>HTML content</h1>')
            ->and($result)->toStartWith("--{$boundary}\r\n")
            ->and($result)->toEndWith("--{$boundary}--");
    });

    it('generates a 32-hex-char boundary', function () {
        expect(MultipartBody::generateBoundary())->toMatch('/^[a-f0-9]{32}$/');
    });

    it('left no boundary state behind on the transport', function () {
        // the mutable per-send field is gone; the boundary is a local now
        expect(property_exists(PhpMailTransport::class, 'boundary'))->toBeFalse();
    });

});

describe('PhpMailTransport::buildHeaders()', function () {

    function phpMailHeaders(Message $message, ?string $boundary = null): string
    {
        $transport = new PhpMailTransport;
        $method = (new ReflectionClass($transport))->getMethod('buildHeaders');

        return $method->invoke($transport, $message, $boundary);
    }

    it('includes plain text Content-Type when no boundary', function () {
        $result = phpMailHeaders(phpMailMessage());

        expect($result)->toContain('Content-Type: text/plain; charset=UTF-8')
            ->and($result)->not->toContain('multipart/alternative');
    });

    it('includes MIME-Version even for plain text, like SmtpTransport always did', function () {
        // RFC 2045: a message carrying any Content-Type header should declare
        // MIME-Version — and this transport always sets a Content-Type. It
        // used to emit MIME-Version only for multipart bodies (recorded
        // drift, aligned 2026-08-17).
        expect(phpMailHeaders(phpMailMessage()))->toContain('MIME-Version: 1.0');
    });

    it('includes multipart/alternative Content-Type with the threaded boundary', function () {
        $boundary = MultipartBody::generateBoundary();
        $result = phpMailHeaders(phpMailMessage(['html' => '<p>HTML</p>']), $boundary);

        expect($result)->toContain('MIME-Version: 1.0')
            ->and($result)->toContain("Content-Type: multipart/alternative; boundary=\"{$boundary}\"");
    });

    it('includes From header', function () {
        $result = phpMailHeaders(phpMailMessage([
            'fromAddress' => 'sender@example.com',
            'fromName' => 'Sender Name',
        ]));

        expect($result)->toContain('From: "Sender Name" <sender@example.com>');
    });

    it('includes Reply-To when set', function () {
        $result = phpMailHeaders(phpMailMessage(['replyTo' => 'reply@example.com']));

        expect($result)->toContain('Reply-To: reply@example.com');
    });

    it('includes CC when set', function () {
        $result = phpMailHeaders(phpMailMessage(['cc' => ['cc1@example.com', 'cc2@example.com']]));

        expect($result)->toContain('Cc: cc1@example.com, cc2@example.com');
    });

    it('includes BCC when set', function () {
        $result = phpMailHeaders(phpMailMessage(['bcc' => ['bcc@example.com']]));

        expect($result)->toContain('Bcc: bcc@example.com');
    });

    it('includes priority headers when set', function () {
        $result = phpMailHeaders(phpMailMessage(['priority' => true]));

        expect($result)->toContain('X-Priority: 1')
            ->and($result)->toContain('X-MSMail-Priority: High')
            ->and($result)->toContain('Importance: High');
    });

});
