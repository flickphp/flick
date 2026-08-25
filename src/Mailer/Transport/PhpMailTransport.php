<?php

declare(strict_types=1);

namespace Flick\Mailer\Transport;

use Flick\Mailer\Message\Message;

/**
 * Transport using PHP's native mail() function.
 */
class PhpMailTransport implements TransportInterface
{
    protected ?string $lastError = null;

    public function send(Message $message): bool
    {
        // This send owns its own error: the field describes THIS attempt, not
        // whatever happened the last time this instance was used.
        $this->lastError = null;

        $to = implode(', ', $message->getTo());
        // PHP's mail() does not RFC-2047 encode the subject; do it here so a
        // non-ASCII subject isn't mangled.
        $subject = $message->getSubjectEncoded();

        // The boundary is per-send scratch, so it lives here as a local and is
        // threaded through - it used to be a mutable instance field coupled to
        // the buildBody-then-buildHeaders call order, with a manual reset.
        $boundary = $message->getHtml() ? MultipartBody::generateBoundary() : null;
        $body = $boundary === null
            ? $message->getText()
            : MultipartBody::build($message, $boundary);
        $headers = $this->buildHeaders($message, $boundary);

        $result = @mail($to, $subject, $body, $headers);

        if (! $result) {
            $this->lastError = 'PHP mail() function failed. Check server mail configuration.';

            return false;
        }

        return true;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    protected function buildHeaders(Message $message, ?string $boundary): string
    {
        $headers = [];

        $headers[] = 'From: '.$message->getFromFormatted();

        // Always declared, not just for multipart: RFC 2045 wants MIME-Version
        // on any message carrying a Content-Type header, and one is always set
        // below. SmtpTransport always emitted it; this transport only did for
        // multipart until the drift was aligned (2026-08-17).
        $headers[] = 'MIME-Version: 1.0';

        if ($boundary) {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        if ($message->getReplyTo()) {
            $headers[] = 'Reply-To: '.$message->getReplyTo();
        }

        if (! empty($message->getCc())) {
            $headers[] = 'Cc: '.implode(', ', $message->getCc());
        }

        if (! empty($message->getBcc())) {
            $headers[] = 'Bcc: '.implode(', ', $message->getBcc());
        }

        if ($message->isPriority()) {
            $headers[] = 'X-Priority: 1';
            $headers[] = 'X-MSMail-Priority: High';
            $headers[] = 'Importance: High';
        }

        return implode("\r\n", $headers);
    }
}
