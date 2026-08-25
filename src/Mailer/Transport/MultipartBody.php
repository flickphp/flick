<?php

declare(strict_types=1);

namespace Flick\Mailer\Transport;

use Flick\Mailer\Message\Message;

/**
 * The one place the multipart/alternative body is assembled.
 *
 * Both transports used to carry their own copy of this block — and
 * PhpMailTransport kept the boundary in a mutable instance field coupled to
 * call order, per-send scratch state on a request-lifetime singleton (audit
 * 2026-08-15, A13). The boundary is now always a local the caller threads
 * through.
 *
 * Deliberately NOT consolidated here: the header lists. They genuinely differ
 * per transport (SMTP writes the To/Subject/Date headers itself; mail() takes
 * those as arguments). The two header drifts that used to live here —
 * MIME-Version placement and X-MSMail-Priority — were aligned 2026-08-17 as
 * their own change, after this consolidation kept them byte-for-byte.
 */
final class MultipartBody
{
    public static function generateBoundary(): string
    {
        return md5(uniqid((string) time()));
    }

    /**
     * The text+html multipart/alternative body, exactly as both transports
     * always emitted it.
     */
    public static function build(Message $message, string $boundary): string
    {
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $message->getText()."\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $message->getHtml()."\r\n\r\n";
        $body .= "--{$boundary}--";

        return $body;
    }
}
