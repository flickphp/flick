<?php

declare(strict_types=1);

use Flick\Mailer\Message\Message;
use Flick\Mailer\Transport\SmtpTransport;

/**
 * The core SMTP transport, driven against a real socket.
 *
 * A throwaway SMTP server (tests/Mailer/Fixtures/fake-smtp-server.php) runs in
 * its own process on an ephemeral port and records every byte the transport
 * sends, so these tests assert on the wire conversation rather than on mocks.
 * Nothing outside PHP is required — no mailpit, no local MTA.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/mail.md, "SMTP Transport"
 * (lines 54-116) and "Methods" (lines 235-271).
 */

/**
 * Handle on the throwaway SMTP server process.
 */
final class FakeSmtpServer
{
    private function __construct(
        public readonly string $host,
        public readonly int $port,
        private mixed $process,
        private array $pipes,
        private string $transcriptPath,
    ) {}

    public static function start(string $scenario = 'ok'): self
    {
        $transcriptPath = tempnam(sys_get_temp_dir(), 'flick-smtp-transcript-');

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = proc_open(
            [PHP_BINARY, __DIR__.'/Fixtures/fake-smtp-server.php', $transcriptPath, $scenario],
            $descriptors,
            $pipes,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the fake SMTP server.');
        }

        $address = fgets($pipes[1]);

        if ($address === false) {
            throw new RuntimeException('Fake SMTP server did not report a port: '.stream_get_contents($pipes[2]));
        }

        [$host, $port] = explode(':', trim($address));

        return new self($host, (int) $port, $process, $pipes, $transcriptPath);
    }

    /**
     * Config block pointing the transport at this server, with encryption off
     * (mail.md line 70: '' means none).
     */
    public function config(array $overrides = []): array
    {
        return array_merge([
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => '',
        ], $overrides);
    }

    /**
     * Everything the transport sent, once the server has finished.
     */
    public function transcript(): string
    {
        $this->stop();

        return (string) file_get_contents($this->transcriptPath);
    }

    public function stop(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($this->process);
        $this->process = null;
    }

    public function cleanUp(): void
    {
        $this->stop();

        if (file_exists($this->transcriptPath)) {
            unlink($this->transcriptPath);
        }
    }
}

function smtpMessage(array $overrides = []): Message
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

/**
 * A TCP port with nothing listening on it: bind, read the port, close.
 */
function closedTcpPort(): int
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    [, $port] = explode(':', stream_socket_get_name($probe, false));
    fclose($probe);

    return (int) $port;
}

afterEach(function () {
    if (isset($this->server) && $this->server instanceof FakeSmtpServer) {
        $this->server->cleanUp();
    }
});

/*
|--------------------------------------------------------------------------
| A successful send
|--------------------------------------------------------------------------
*/

it('reports success when the server accepts the message', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    expect($transport->send(smtpMessage()))->toBeTrue()
        ->and($transport->getLastError())->toBeNull();
});

it('walks the whole SMTP conversation', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage());

    $transcript = $this->server->transcript();

    expect($transcript)->toContain('EHLO')
        ->and($transcript)->toContain('MAIL FROM:<from@example.com>')
        ->and($transcript)->toContain('RCPT TO:<to@example.com>')
        ->and($transcript)->toContain("DATA\r\n")
        ->and($transcript)->toContain("\r\n.\r\n");
});

it('sends QUIT when it disconnects', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage());

    expect($this->server->transcript())->toContain("QUIT\r\n");
});

it('puts the subject and body on the wire', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage(['subject' => 'Order confirmed', 'text' => 'Thanks for your order.']));

    $transcript = $this->server->transcript();

    expect($transcript)->toContain('Subject: Order confirmed')
        ->and($transcript)->toContain('Thanks for your order.');
});

it('sends an html message as multipart alternative', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage(['html' => '<h1>Thanks!</h1>']));

    $transcript = $this->server->transcript();

    expect($transcript)->toContain('multipart/alternative')
        ->and($transcript)->toContain('<h1>Thanks!</h1>')
        ->and($transcript)->toContain('Thanks for your order.');
});

it('sends one RCPT TO per recipient', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage(['to' => ['one@example.com', 'two@example.com']]));

    $transcript = $this->server->transcript();

    expect($transcript)->toContain('RCPT TO:<one@example.com>')
        ->and($transcript)->toContain('RCPT TO:<two@example.com>');
});

it('delivers bcc recipients as envelope recipients without naming them in the headers', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage(['bcc' => ['hidden@example.com']]));

    $transcript = $this->server->transcript();
    [, $headersAndBody] = explode("DATA\r\n", $transcript, 2);

    expect($transcript)->toContain('RCPT TO:<hidden@example.com>')
        ->and($headersAndBody)->not->toContain('hidden@example.com');
});

it('names cc and reply-to recipients in the headers', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage([
        'cc' => ['copy@example.com'],
        'replyTo' => 'reply@example.com',
    ]));

    $transcript = $this->server->transcript();

    expect($transcript)->toContain('Cc: copy@example.com')
        ->and($transcript)->toContain('Reply-To: reply@example.com')
        ->and($transcript)->toContain('RCPT TO:<copy@example.com>');
});

/*
|--------------------------------------------------------------------------
| Header injection
|--------------------------------------------------------------------------
*/

it('does not let a CRLF in the subject open a new header', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage([
        'subject' => "Order confirmed\r\nBcc: evil@example.com",
    ]));

    $transcript = $this->server->transcript();

    expect($transcript)->not->toContain("\r\nBcc: evil@example.com")
        ->and($transcript)->not->toContain('RCPT TO:<evil@example.com>');
});

it('does not let a CRLF in the from name open a new header', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage([
        'fromName' => "Flick\r\nBcc: evil@example.com",
    ]));

    expect($this->server->transcript())->not->toContain("\r\nBcc: evil@example.com");
});

it('does not let a CRLF in the reply-to open a new header', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage([
        'replyTo' => "reply@example.com\r\nBcc: evil@example.com",
    ]));

    expect($this->server->transcript())->not->toContain("\r\nBcc: evil@example.com");
});

it('does not let a CRLF in a recipient open a new envelope recipient', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage([
        'to' => ["to@example.com\r\nRCPT TO:<evil@example.com>"],
    ]));

    $transcript = $this->server->transcript();

    // The injected text survives as part of one (broken) address rather than
    // starting a second RCPT TO command, which is what a CRLF would buy an
    // attacker. A command only counts if it begins its own line.
    expect($transcript)->not->toContain("\r\nRCPT TO:<evil@example.com>")
        ->and($transcript)->toContain('RCPT TO:<to@example.comRCPT TO:<evil@example.com>>');
});

/*
|--------------------------------------------------------------------------
| Failure paths — mail.md line 270: send() returns false on failure
|--------------------------------------------------------------------------
*/

it('reports a refused connection instead of throwing', function () {
    $transport = new SmtpTransport([
        'transport' => 'smtp',
        'host' => '127.0.0.1',
        'port' => closedTcpPort(),
        'encryption' => '',
        'timeout' => 2,
    ]);

    // fsockopen() raises a diagnostic on a refused connection. The transport
    // silences it with @, but PHPUnit's error handler still records suppressed
    // diagnostics and this suite runs with failOnWarning. Swap in a no-op
    // handler for the duration of the call, then hand the suite back its own.
    set_error_handler(fn (): bool => true);

    try {
        $sent = $transport->send(smtpMessage());
    } finally {
        restore_error_handler();
    }

    expect($sent)->toBeFalse()
        ->and($transport->getLastError())->toBeString()
        ->and($transport->getLastError())->toContain('SMTP connection failed');
});

it('reports a server that refuses the connection with a non-220 greeting', function () {
    $this->server = FakeSmtpServer::start('greeting-fail');
    $transport = new SmtpTransport($this->server->config());

    expect($transport->send(smtpMessage()))->toBeFalse()
        ->and($transport->getLastError())->toContain('554');
});

it('reports a server that rejects the message body', function () {
    $this->server = FakeSmtpServer::start('data-fail');
    $transport = new SmtpTransport($this->server->config());

    expect($transport->send(smtpMessage()))->toBeFalse()
        ->and($transport->getLastError())->toContain('554');
});

/*
|--------------------------------------------------------------------------
| Authentication — mail.md lines 71-75 and 101-107
|--------------------------------------------------------------------------
*/

it('authenticates when credentials are given and insecure auth is allowed', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config([
        'username' => 'flick',
        'password' => 'secret',
        'allowInsecureAuth' => true,
    ]));

    $sent = $transport->send(smtpMessage());
    $transcript = $this->server->transcript();

    expect($sent)->toBeTrue()
        ->and($transcript)->toContain('AUTH');
});

it('surfaces a useful message when authentication fails', function () {
    $this->server = FakeSmtpServer::start('auth-fail');
    $transport = new SmtpTransport($this->server->config([
        'username' => 'flick',
        'password' => 'wrong',
        'allowInsecureAuth' => true,
    ]));

    expect($transport->send(smtpMessage()))->toBeFalse()
        ->and($transport->getLastError())->toContain('535');
});

it('refuses to send credentials over an unencrypted connection', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config([
        'username' => 'flick',
        'password' => 'secret',
    ]));

    expect($transport->send(smtpMessage()))->toBeFalse()
        ->and($transport->getLastError())
        ->toContain('Refusing to send SMTP credentials over an unencrypted connection');
});

it('skips authentication entirely when no credentials are configured', function () {
    $this->server = FakeSmtpServer::start();
    $transport = new SmtpTransport($this->server->config());

    $transport->send(smtpMessage());

    expect($this->server->transcript())->not->toContain('AUTH');
});

/*
|--------------------------------------------------------------------------
| Encryption policy (Audit-081701 S07, widened to core)
|--------------------------------------------------------------------------
|
| Core resolved 'encryption' with a bare if/elseif chain, so three values that
| are not 'ssl' or 'tls' reached the else branch and sent plaintext with no
| STARTTLS attempt at all: a typo, 'starttls' (which pro accepts as an alias),
| and null. The same config was encrypted under pro and plaintext here.
|
| The fake server answers STARTTLS with '454 TLS not available', so a transport
| that genuinely requires STARTTLS must fail the send rather than continue in
| the clear. That is what makes these assertions meaningful.
|
| Spec: docs/guide/mail.md:70 ('' means none) and docs/services/mail.md:75-81
| ('none' means none).
*/

test('rejects an unknown encryption value at construction', function () {
    expect(fn () => new SmtpTransport(['host' => 'localhost', 'encryption' => 'tsl']))
        ->toThrow(InvalidArgumentException::class);
});

test('names the offending value and the supported set when encryption is unknown', function () {
    try {
        new SmtpTransport(['host' => 'localhost', 'encryption' => 'tsl']);
        $this->fail('expected an InvalidArgumentException');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toContain('tsl')
            ->and($exception->getMessage())->toContain('tls')
            ->and($exception->getMessage())->toContain('none');
    }
});

test('starttls requires encryption instead of silently sending in the clear', function () {
    $server = FakeSmtpServer::start();

    try {
        $transport = new SmtpTransport($server->config(['encryption' => 'starttls']));
        $sent = $transport->send(smtpMessage());
        $transcript = $server->transcript();

        // The server cannot do TLS, so a transport that requires it must not send.
        expect($sent)->toBeFalse()
            ->and($transcript)->toContain('STARTTLS')
            ->and($transcript)->not->toContain('Thanks for your order.');
    } finally {
        $server->cleanUp();
    }
});

test('a null encryption value takes the secure default rather than meaning none', function () {
    $server = FakeSmtpServer::start();

    try {
        $transport = new SmtpTransport($server->config(['encryption' => null]));
        $sent = $transport->send(smtpMessage());
        $transcript = $server->transcript();

        expect($sent)->toBeFalse()
            ->and($transcript)->toContain('STARTTLS')
            ->and($transcript)->not->toContain('Thanks for your order.');
    } finally {
        $server->cleanUp();
    }
});

test('an absent encryption key requires STARTTLS', function () {
    $server = FakeSmtpServer::start();

    try {
        $config = $server->config();
        unset($config['encryption']);

        $transport = new SmtpTransport($config);
        $sent = $transport->send(smtpMessage());

        expect($sent)->toBeFalse()
            ->and($server->transcript())->toContain('STARTTLS');
    } finally {
        $server->cleanUp();
    }
});

test('none sends without attempting STARTTLS', function () {
    $server = FakeSmtpServer::start();

    try {
        $transport = new SmtpTransport($server->config(['encryption' => 'none']));
        $sent = $transport->send(smtpMessage());
        $transcript = $server->transcript();

        expect($sent)->toBeTrue()
            ->and($transcript)->not->toContain('STARTTLS')
            ->and($transcript)->toContain('Thanks for your order.');
    } finally {
        $server->cleanUp();
    }
});
