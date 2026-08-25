<?php

declare(strict_types=1);

namespace Flick\Mailer\Transport;

use Flick\Mailer\Message\Message;

interface TransportInterface
{
    /**
     * Send an email message.
     *
     * @param  Message  $message  The message to send
     * @return bool True on success, false on failure
     */
    public function send(Message $message): bool;

    /**
     * Get the last error message if send() returned false.
     */
    public function getLastError(): ?string;
}
