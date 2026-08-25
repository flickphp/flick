<?php

declare(strict_types=1);

namespace Flick\Mailer\Transport;

use Flick\Mailer\Message\Message;
use InvalidArgumentException;
use RuntimeException;

/**
 * Socket-based SMTP transport without external dependencies.
 */
class SmtpTransport implements TransportInterface
{
    /** STARTTLS is required; the send fails rather than continuing in the clear. */
    private const TLS_REQUIRED = 'required';

    /** Implicit TLS on connect (ssl://), so STARTTLS does not apply. */
    private const TLS_IMPLICIT = 'implicit';

    /** Explicitly unencrypted. */
    private const TLS_DISABLED = 'disabled';

    protected array $config;

    protected ?string $lastError = null;

    /** @var resource|null */
    protected $socket = null;

    /** True once the connection is TLS/SSL protected. */
    protected bool $encrypted = false;

    /** Resolved once at construction; see resolveTlsPolicy(). */
    protected string $tlsPolicy;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Resolved here, not in connect(), so a bad value surfaces when the
        // service is built instead of being swallowed by send()'s catch and
        // reported as a failed delivery.
        $this->tlsPolicy = $this->resolveTlsPolicy($config['encryption'] ?? 'tls');
    }

    /**
     * Resolve the configured encryption mode to an explicit TLS policy.
     *
     * Closed on purpose. This used to be a bare if/elseif chain on 'ssl' and
     * 'tls', so everything else fell through to plaintext with no STARTTLS
     * attempt at all: a typo, and 'starttls', which Pro's transport accepts as
     * an alias for 'tls'. The same config was encrypted under Pro and in the
     * clear here.
     *
     * - absent or null       -> 'tls' (no value given takes the secure default)
     * - 'tls' / 'starttls'   -> STARTTLS required
     * - 'ssl'                -> implicit TLS on connect
     * - 'none' / ''          -> explicitly unencrypted. Both spellings are
     *                           documented: '' in docs/guide/mail.md:70,
     *                           'none' in docs/services/mail.md:81.
     * - anything else        -> InvalidArgumentException.
     *
     * null previously meant "no encryption" here. It is documented nowhere, and
     * reading it as plaintext is the same silent downgrade this closes, so it
     * now takes the secure default. 'none' and '' remain the documented ways to
     * turn encryption off.
     */
    protected function resolveTlsPolicy(mixed $encryption): string
    {
        return match ($encryption) {
            'tls', 'starttls' => self::TLS_REQUIRED,
            'ssl' => self::TLS_IMPLICIT,
            'none', '' => self::TLS_DISABLED,
            default => throw new InvalidArgumentException(sprintf(
                "Mailer 'encryption' value %s is not supported. Use 'tls' (or 'starttls'), ".
                "'ssl', or 'none' (or '') to send unencrypted.",
                is_string($encryption) ? "'{$encryption}'" : get_debug_type($encryption)
            )),
        };
    }

    public function send(Message $message): bool
    {
        // This send owns its own error: the field describes THIS attempt, not
        // whatever happened the last time this instance was used.
        $this->lastError = null;

        try {
            $this->connect();
            $this->authenticate();
            $this->sendMessage($message);
            $this->disconnect();

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();

            return false;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    protected function connect(): void
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 587;
        $timeout = $this->config['timeout'] ?? 30;

        // Peer verification is on by default; it may be disabled for local development.
        $verifyPeer = $this->config['verifyPeer'] ?? true;

        if ($this->tlsPolicy === self::TLS_IMPLICIT) {
            // Implicit TLS (typically port 465). fsockopen('ssl://…') can't attach a
            // verification context, so use stream_socket_client and verify the cert.
            $context = stream_context_create(['ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ]]);

            $this->socket = @stream_socket_client(
                'ssl://'.$host.':'.$port,
                $errno,
                $errstr,
                (float) $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $this->socket) {
                throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
            }

            $this->encrypted = true;
        } else {
            $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if (! $this->socket) {
                throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
            }
        }

        // fsockopen's timeout only covers the connect; apply it to reads/writes too
        stream_set_timeout($this->socket, (int) $timeout);

        $this->readResponse(220);
        $this->sendCommand('EHLO '.gethostname(), 250);

        if ($this->tlsPolicy === self::TLS_REQUIRED) {
            $this->sendCommand('STARTTLS', 220);

            if (! $verifyPeer) {
                stream_context_set_option($this->socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($this->socket, 'ssl', 'verify_peer_name', false);
            }
            stream_context_set_option($this->socket, 'ssl', 'peer_name', $host);

            if (! stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            $this->encrypted = true;
            $this->sendCommand('EHLO '.gethostname(), 250);
        }
    }

    protected function authenticate(): void
    {
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';

        if ($username && $password) {
            // Never send credentials in the clear. Require an encrypted channel unless
            // the operator explicitly opts in (e.g. a trusted relay on localhost).
            if (! $this->encrypted && empty($this->config['allowInsecureAuth'])) {
                throw new RuntimeException('Refusing to send SMTP credentials over an unencrypted connection. Use TLS/SSL, or set allowInsecureAuth => true to override.');
            }

            $this->sendCommand('AUTH LOGIN', 334);
            $this->sendCommand(base64_encode($username), 334);
            $this->sendCommand(base64_encode($password), 235);
        }
    }

    protected function sendMessage(Message $message): void
    {
        $this->sendCommand("MAIL FROM:<{$message->getFromAddress()}>", 250);

        foreach ($message->getTo() as $to) {
            $this->sendCommand("RCPT TO:<{$to}>", 250);
        }

        foreach ($message->getCc() as $cc) {
            $this->sendCommand("RCPT TO:<{$cc}>", 250);
        }

        foreach ($message->getBcc() as $bcc) {
            $this->sendCommand("RCPT TO:<{$bcc}>", 250);
        }

        $this->sendCommand('DATA', 354);

        $data = $this->prepareDataForTransmission($this->buildEmailData($message));
        fwrite($this->socket, $data."\r\n.\r\n");
        $this->readResponse(250);
    }

    /**
     * Prepare DATA payload per RFC 5321: normalize all line endings to CRLF and
     * dot-stuff so a body line of "." can't be read as the end-of-DATA marker.
     */
    protected function prepareDataForTransmission(string $data): string
    {
        // normalize CR, LF, and CRLF to CRLF without doubling existing CRLF
        $data = preg_replace('/\r\n|\r|\n/', "\r\n", $data);

        // any line beginning with "." gets an extra leading "."
        return preg_replace('/^\./m', '..', $data);
    }

    protected function buildEmailData(Message $message): string
    {
        $headers = [];
        $headers[] = 'From: '.$message->getFromFormatted();
        $headers[] = 'To: '.implode(', ', $message->getTo());
        $headers[] = 'Subject: '.$message->getSubjectEncoded();
        $headers[] = 'Date: '.date('r');
        $headers[] = 'MIME-Version: 1.0';

        if (! empty($message->getCc())) {
            $headers[] = 'Cc: '.implode(', ', $message->getCc());
        }

        if ($message->getReplyTo()) {
            $headers[] = 'Reply-To: '.$message->getReplyTo();
        }

        if ($message->isPriority()) {
            // Same three-header set as PhpMailTransport (X-MSMail-Priority is
            // the legacy Outlook spelling; harmless elsewhere). This transport
            // lacked it until the drift was aligned (2026-08-17).
            $headers[] = 'X-Priority: 1';
            $headers[] = 'X-MSMail-Priority: High';
            $headers[] = 'Importance: High';
        }

        if ($message->getHtml()) {
            $boundary = MultipartBody::generateBoundary();
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $body = MultipartBody::build($message, $boundary);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = $message->getText();
        }

        return implode("\r\n", $headers)."\r\n\r\n".$body;
    }

    protected function sendCommand(string $command, int $expectedCode): void
    {
        fwrite($this->socket, $command."\r\n");
        $this->readResponse($expectedCode);
    }

    protected function readResponse(int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP error: expected {$expectedCode}, got {$code} - ".trim($response));
        }

        return $response;
    }

    protected function disconnect(): void
    {
        if ($this->socket) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
