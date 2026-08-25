<?php

/**
 * Drives PhpMailTransport::send() in its own process.
 *
 * mail() delivers through the sendmail_path ini setting, which is
 * PHP_INI_SYSTEM and so cannot be changed from inside a test. Running here
 * under `php -d sendmail_path=...` lets the test decide whether the send
 * succeeds or fails without depending on whatever MTA the machine happens to
 * have — the same result on a laptop and on CI.
 *
 * Prints one line of JSON: {"sent": bool, "error": string|null,
 * "sendmailPath": string}
 *
 * sendmailPath is what this process actually resolved the -d value to, which
 * is not always what the parent passed: an INI value is not taken literally,
 * so a path containing one of its operators (~ | & ^ parentheses) can be
 * truncated silently. Reporting it turns "the stub never ran" into a visible
 * cause.
 */

declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';

use Flick\Mailer\Message\Message;
use Flick\Mailer\Transport\PhpMailTransport;

$options = json_decode($argv[1] ?? '{}', true) ?: [];

$transport = new PhpMailTransport;

$sent = $transport->send(new Message(
    fromAddress: $options['fromAddress'] ?? 'from@example.com',
    fromName: $options['fromName'] ?? 'Flick Sender',
    to: $options['to'] ?? ['to@example.com'],
    subject: $options['subject'] ?? 'Order confirmed',
    text: $options['text'] ?? 'Thanks for your order.',
    html: $options['html'] ?? null,
    cc: $options['cc'] ?? [],
    bcc: $options['bcc'] ?? [],
    replyTo: $options['replyTo'] ?? null,
    priority: $options['priority'] ?? false,
));

echo json_encode([
    'sent' => $sent,
    'error' => $transport->getLastError(),
    'sendmailPath' => (string) ini_get('sendmail_path'),
]);
