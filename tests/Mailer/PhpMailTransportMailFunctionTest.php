<?php

declare(strict_types=1);

namespace Flick\Mailer\Transport;

use Flick\Mailer\Message\Message;

/**
 * PhpMailTransport::send(), in-process.
 *
 * send() calls mail() unqualified from inside this namespace, so PHP looks for
 * Flick\Mailer\Transport\mail() before falling back to the global one. Defining
 * it here puts a stub in front of the real function: both branches run without
 * an MTA, and the exact arguments handed to mail() can be inspected.
 *
 * PhpMailTransportSendTest.php covers the same method against the real mail()
 * in a child process, so this stub can never be the only thing being tested.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/mail.md, "Methods"
 * (lines 235-271).
 */
final class MailFunctionSpy
{
    /** @var array<int, array{to: string, subject: string, message: string, headers: string}> */
    public static array $calls = [];

    public static bool $result = true;

    public static function reset(): void
    {
        self::$calls = [];
        self::$result = true;
    }

    /** @return array{to: string, subject: string, message: string, headers: string} */
    public static function lastCall(): array
    {
        return self::$calls[array_key_last(self::$calls)];
    }
}

function mail(string $to, string $subject, string $message, string $headers = '', string $params = ''): bool
{
    MailFunctionSpy::$calls[] = compact('to', 'subject', 'message', 'headers');

    return MailFunctionSpy::$result;
}

function phpMailMessage(array $overrides = []): Message
{
    return new Message(
        fromAddress: $overrides['fromAddress'] ?? 'from@example.com',
        fromName: $overrides['fromName'] ?? 'Flick Sender',
        to: $overrides['to'] ?? ['to@example.com'],
        subject: $overrides['subject'] ?? 'Order confirmed',
        text: $overrides['text'] ?? 'Thanks for your order.',
        html: $overrides['html'] ?? null,
        cc: $overrides['cc'] ?? [],
        bcc: $overrides['bcc'] ?? [],
        replyTo: $overrides['replyTo'] ?? null,
        priority: $overrides['priority'] ?? false,
    );
}

beforeEach(function () {
    MailFunctionSpy::reset();
});

it('reports success when mail() accepts the message', function () {
    MailFunctionSpy::$result = true;
    $transport = new PhpMailTransport;

    expect($transport->send(phpMailMessage()))->toBeTrue()
        ->and($transport->getLastError())->toBeNull();
});

it('reports failure when mail() rejects the message', function () {
    MailFunctionSpy::$result = false;
    $transport = new PhpMailTransport;

    expect($transport->send(phpMailMessage()))->toBeFalse()
        ->and($transport->getLastError())->toBe('PHP mail() function failed. Check server mail configuration.');
});

it('hands mail() the recipients as a comma separated list', function () {
    (new PhpMailTransport)->send(phpMailMessage(['to' => ['one@example.com', 'two@example.com']]));

    expect(MailFunctionSpy::lastCall()['to'])->toBe('one@example.com, two@example.com');
});

it('hands mail() the subject and body', function () {
    (new PhpMailTransport)->send(phpMailMessage());

    $call = MailFunctionSpy::lastCall();

    expect($call['subject'])->toBe('Order confirmed')
        ->and($call['message'])->toBe('Thanks for your order.');
});

it('rfc-2047 encodes a non-ascii subject before handing it to mail()', function () {
    (new PhpMailTransport)->send(phpMailMessage(['subject' => 'Grüße']));

    expect(MailFunctionSpy::lastCall()['subject'])->toContain('=?UTF-8?B?')
        ->and(MailFunctionSpy::lastCall()['subject'])->not->toContain('Grüße');
});

it('hands mail() the sender, cc and reply-to headers', function () {
    (new PhpMailTransport)->send(phpMailMessage([
        'cc' => ['copy@example.com'],
        'replyTo' => 'reply@example.com',
    ]));

    $headers = MailFunctionSpy::lastCall()['headers'];

    expect($headers)->toContain('From: "Flick Sender" <from@example.com>')
        ->and($headers)->toContain('Cc: copy@example.com')
        ->and($headers)->toContain('Reply-To: reply@example.com');
});

it('hands mail() a multipart body when the message has html', function () {
    (new PhpMailTransport)->send(phpMailMessage(['html' => '<h1>Thanks!</h1>']));

    $call = MailFunctionSpy::lastCall();

    expect($call['headers'])->toContain('multipart/alternative')
        ->and($call['message'])->toContain('<h1>Thanks!</h1>')
        ->and($call['message'])->toContain('Thanks for your order.');
});

it('does not let a CRLF in the from name or reply-to open a new header', function () {
    (new PhpMailTransport)->send(phpMailMessage([
        'fromName' => "Flick\r\nBcc: evil@example.com",
        'replyTo' => "reply@example.com\r\nBcc: eviltoo@example.com",
    ]));

    // The injected text survives as part of one header value; what must not
    // survive is the line break that would turn it into a header of its own.
    $headers = MailFunctionSpy::lastCall()['headers'];

    expect($headers)->not->toContain("\r\nBcc: evil@example.com")
        ->and($headers)->not->toContain("\r\nBcc: eviltoo@example.com");
});

it('does not let a CRLF in a recipient open a new address', function () {
    (new PhpMailTransport)->send(phpMailMessage([
        'to' => ["to@example.com\r\nBcc: evil@example.com"],
    ]));

    expect(MailFunctionSpy::lastCall()['to'])->not->toContain("\r\n");
});
