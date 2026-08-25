<?php

declare(strict_types=1);

namespace Flick\Mailer;

use Flick\Mailer\Message\FormDataFormatter;
use Flick\Mailer\Message\Message;
use Flick\Mailer\Transport\TransportInterface;
use Flick\Support\Support;
use InvalidArgumentException;
use RuntimeException;

/**
 * Mail service for sending emails.
 * Free tier: PHP mail() and basic SMTP only.
 * Upgrade to Flick Pro for attachments, templates, and more transports.
 */
class Mailer
{
    protected array $config;

    protected Support $support;

    protected TransportInterface $transport;

    protected FormDataFormatter $formDataFormatter;

    public function __construct(
        array $config,
        Support $support,
        TransportInterface $transport,
        FormDataFormatter $formDataFormatter
    ) {
        $this->config = $config;
        $this->support = $support;
        $this->transport = $transport;
        $this->formDataFormatter = $formDataFormatter;
    }

    /**
     * Send an email.
     *
     * @param  string|array  $to  Recipient(s)
     * @param  string  $subject  Email subject
     * @param  string  $body  Plain text message
     * @param  array  $options  Optional: html, data, cc, bcc, replyTo, fromAddress, fromName, priority
     * @return bool True on success, false on failure (check $form->getError('mail'))
     *
     * @throws InvalidArgumentException If options include attachments (requires Flick Pro)
     */
    public function send(string|array $to, string $subject, string $body, array $options = []): bool
    {
        if (isset($options['attachments'])) {
            throw new InvalidArgumentException(
                'Email attachments require Flick Pro. '.
                'Upgrade at https://flickphp.com/pro for attachments, templates, and more transport options.'
            );
        }

        try {
            $message = $this->buildMessage($to, $subject, $body, $options);

            if (! $this->addressesAreValid($message)) {
                return false;
            }

            $result = $this->transport->send($message);

            if (! $result) {
                $this->support->addError('mail', $this->transport->getLastError() ?? $this->support->message('MailSendFailed'));

                return false;
            }

            return true;
        } catch (RuntimeException $e) {
            $this->support->addError('mail', $e->getMessage());

            return false;
        }
    }

    /**
     * Send form data as a formatted email.
     *
     * @param  string|array  $to  Recipient(s)
     * @param  string  $subject  Email subject
     * @param  array  $formData  Key-value pairs of form data
     * @param  array  $options  Same options as send(), plus 'exclude' for keys to omit
     */
    public function sendFormData(string|array $to, string $subject, array $formData, array $options = []): bool
    {
        $exclude = $options['exclude'] ?? [];
        unset($options['exclude']);

        $textBody = $this->formDataFormatter->toText($formData, $exclude);
        $htmlBody = $this->formDataFormatter->toHtml($formData, $exclude);

        // Only set html if not already provided
        if (! isset($options['html'])) {
            $options['html'] = $htmlBody;
        }

        return $this->send($to, $subject, $textBody, $options);
    }

    /**
     * Add an attachment for the next send() call.
     *
     * @throws InvalidArgumentException Attachments require Flick Pro
     */
    public function attach(string $path, ?string $name = null): self
    {
        throw new InvalidArgumentException(
            'Email attachments require Flick Pro. '.
            'Upgrade at https://flickphp.com/pro for attachments, templates, and more transport options.'
        );
    }

    /**
     * Add inline content as an attachment.
     *
     * @throws InvalidArgumentException Attachments require Flick Pro
     */
    public function attachContent(string $content, string $name, string $mimeType): self
    {
        throw new InvalidArgumentException(
            'Email attachments require Flick Pro. '.
            'Upgrade at https://flickphp.com/pro for attachments, templates, and more transport options.'
        );
    }

    /**
     * Clear all pending attachments.
     *
     * @throws InvalidArgumentException Attachments require Flick Pro
     */
    public function clearAttachments(): self
    {
        throw new InvalidArgumentException(
            'Email attachments require Flick Pro. '.
            'Upgrade at https://flickphp.com/pro for attachments, templates, and more transport options.'
        );
    }

    protected function buildMessage(string|array $to, string $subject, string $body, array $options): Message
    {
        // Through the same normalizer cc and bcc use: '' has to mean "no recipient",
        // not a recipient whose address is the empty string.
        $recipients = $this->normalizeRecipients($to);

        // Get from address/name from options or config
        $fromAddress = $options['fromAddress'] ?? $this->config['fromAddress'] ?? '';
        $fromName = $options['fromName'] ?? $this->config['fromName'] ?? '';

        // Process body text
        $text = $body;
        $html = $options['html'] ?? null;

        // Simple template variable replacement for {{ variable }}
        if (isset($options['data']) && is_array($options['data'])) {
            $data = $options['data'];

            foreach ($data as $key => $value) {
                if (is_scalar($value)) {
                    $stringValue = (string) $value;
                    $text = str_replace('{{ '.$key.' }}', $stringValue, $text);
                    $text = str_replace('{{'.$key.'}}', $stringValue, $text);

                    if ($html) {
                        $escapedValue = htmlspecialchars($stringValue, ENT_QUOTES, 'UTF-8');
                        $html = str_replace('{{ '.$key.' }}', $escapedValue, $html);
                        $html = str_replace('{{'.$key.'}}', $escapedValue, $html);
                    }
                }
            }
        }

        // Get CC/BCC/ReplyTo from options or config
        $cc = $this->normalizeRecipients($options['cc'] ?? $this->config['cc'] ?? []);
        $bcc = $this->normalizeRecipients($options['bcc'] ?? $this->config['bcc'] ?? []);
        $replyTo = $options['replyTo'] ?? $this->config['replyTo'] ?? null;
        $priority = (bool) ($options['priority'] ?? $this->config['priority'] ?? false);

        return new Message(
            fromAddress: $fromAddress,
            fromName: $fromName,
            to: $recipients,
            subject: $subject,
            text: $text,
            html: $html,
            cc: $cc,
            bcc: $bcc,
            replyTo: $replyTo,
            priority: $priority,
        );
    }

    /**
     * Normalize recipients to array format.
     *
     * This owns what "no recipient" means, so it owns both spellings of it. The
     * scalar branch mapped '' to [] while arrays came back untouched, which left
     * [''] as a recipient whose address is the empty string: addressesAreValid()
     * skips empty strings, so it validated, and SMTP then wrote a literal
     * RCPT TO:<> to the wire for every empty slot. After this, [''], '' and []
     * are the same list.
     */
    protected function normalizeRecipients(string|array|null $recipients): array
    {
        if ($recipients === null || $recipients === '') {
            return [];
        }

        if (! is_array($recipients)) {
            return [$recipients];
        }

        // array_values so the result stays a list - ['', 'a@b.com'] must not
        // come back as [1 => 'a@b.com'].
        return array_values(array_filter(
            $recipients,
            static fn ($address): bool => $address !== null && $address !== ''
        ));
    }

    /**
     * Validate every address-bearing header. A malformed address (including one
     * carrying CR/LF from submitted input) is rejected before anything is sent.
     */
    protected function addressesAreValid(Message $message): bool
    {
        $addresses = array_merge(
            [$message->getFromAddress()],
            $message->getTo(),
            $message->getCc(),
            $message->getBcc(),
        );

        if ($message->getReplyTo() !== null && $message->getReplyTo() !== '') {
            $addresses[] = $message->getReplyTo();
        }

        foreach ($addresses as $address) {
            if ($address === '') {
                continue;
            }

            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $this->support->addError('mail', $this->support->message('MailInvalidAddress', ['address' => $address]));

                return false;
            }
        }

        return true;
    }
}
