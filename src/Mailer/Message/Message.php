<?php

declare(strict_types=1);

namespace Flick\Mailer\Message;

/**
 * Value object representing an email message.
 */
class Message
{
    public function __construct(
        protected string $fromAddress,
        protected string $fromName,
        protected array $to,
        protected string $subject,
        protected string $text,
        protected ?string $html = null,
        protected array $cc = [],
        protected array $bcc = [],
        protected ?string $replyTo = null,
        protected bool $priority = false,
    ) {
        // Strip CR/LF from every header-bound field so submitted input can't
        // inject extra header lines. Body fields (text, html) keep their newlines.
        $this->fromAddress = self::stripHeaderInjection($this->fromAddress);
        $this->fromName = self::stripHeaderInjection($this->fromName);
        $this->subject = self::stripHeaderInjection($this->subject);
        $this->to = self::stripAddresses($this->to);
        $this->cc = self::stripAddresses($this->cc);
        $this->bcc = self::stripAddresses($this->bcc);
        $this->replyTo = $this->replyTo === null ? null : self::stripHeaderInjection($this->replyTo);
    }

    private static function stripHeaderInjection(string $value): string
    {
        return preg_replace('/[\r\n]+/', '', $value);
    }

    /**
     * Strip every address, then drop any the strip emptied. Recipients are
     * normalized before they get here, so an address that was nothing but
     * CR/LF arrives intact, becomes '' on the strip, and - left in the list -
     * slips past the validator's empty-string skip onto the wire as RCPT TO:<>.
     */
    private static function stripAddresses(array $addresses): array
    {
        $stripped = array_map([self::class, 'stripHeaderInjection'], $addresses);

        return array_values(array_filter($stripped, fn (string $address): bool => $address !== ''));
    }

    /**
     * Get formatted "From" header string.
     */
    public function getFromFormatted(): string
    {
        if ($this->fromName !== '') {
            // RFC-2047 encode a non-ASCII display name; mb_encode_mimeheader
            // returns ASCII names unchanged, so those keep the quoted form.
            $encoded = mb_encode_mimeheader($this->fromName, 'UTF-8', 'B');

            if ($encoded !== $this->fromName) {
                return sprintf('%s <%s>', $encoded, $this->fromAddress);
            }

            return sprintf('"%s" <%s>', str_replace('"', '\\"', $this->fromName), $this->fromAddress);
        }

        return $this->fromAddress;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    /**
     * @return string[]
     */
    public function getTo(): array
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * The subject RFC-2047 encoded for use in a mail header. A non-ASCII subject
     * becomes an encoded word (=?UTF-8?B?...?=); an ASCII subject is unchanged.
     */
    public function getSubjectEncoded(): string
    {
        return mb_encode_mimeheader($this->subject, 'UTF-8', 'B');
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    /**
     * @return string[]
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * @return string[]
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function isPriority(): bool
    {
        return $this->priority;
    }
}
