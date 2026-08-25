<?php

use Flick\Mailer\Mailer;
use Flick\Mailer\Message\FormDataFormatter;
use Flick\Mailer\Message\Message;
use Flick\Mailer\Transport\TransportInterface;
use Flick\Support\Errors;
use Flick\Support\Support;

beforeEach(function () {
    $this->transport = Mockery::mock(TransportInterface::class);
    $this->formDataFormatter = new FormDataFormatter;
    // A partial over a real Support: addError() is stubbed, message() runs for
    // real so the error texts Mailer reads from lang/en/messages.php resolve.
    $this->support = Mockery::mock(Support::class, [[], new Errors])->makePartial();
    $this->support->shouldReceive('addError')->andReturn(null);

    $this->config = [
        'fromAddress' => 'noreply@example.com',
        'fromName' => 'Test App',
    ];

    $this->mailer = new Mailer(
        $this->config,
        $this->support,
        $this->transport,
        $this->formDataFormatter
    );
});

afterEach(function () {
    Mockery::close();
});

describe('Mailer::send()', function () {

    it('sends simple email', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test Subject', 'Test body');

        expect($result)->toBeTrue();
    });

    it('returns false on transport failure', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->andReturn(false);
        $this->transport->shouldReceive('getLastError')
            ->once()
            ->andReturn('SMTP connection failed');

        $result = $this->mailer->send('user@example.com', 'Test Subject', 'Test body');

        expect($result)->toBeFalse();
    });

    it('returns false and adds a mail error when the transport throws', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP connect failed'));

        // Dedicated double: the shared $this->support stub from beforeEach() accepts
        // addError() with any args, so it would swallow this call before a stricter
        // ->with() expectation on it ever got checked (Mockery matches expectations
        // in the order they were added). Build a fresh one to assert the message.
        $support = Mockery::mock(Support::class);
        $support->shouldReceive('addError')
            ->once()
            ->with('mail', Mockery::pattern('/SMTP connect failed/'));

        $mailer = new Mailer($this->config, $support, $this->transport, $this->formDataFormatter);

        $result = $mailer->send('user@example.com', 'Test Subject', 'Test body');

        expect($result)->toBeFalse();
    });

    it('sends email with html content', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getHtml() === '<h1>Hello</h1>';
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Plain text', [
            'html' => '<h1>Hello</h1>',
        ]);

        expect($result)->toBeTrue();
    });

    it('sends email to multiple recipients', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return count($message->getTo()) === 3;
            })
            ->andReturn(true);

        $result = $this->mailer->send(
            ['user1@example.com', 'user2@example.com', 'user3@example.com'],
            'Test',
            'Body'
        );

        expect($result)->toBeTrue();
    });

    it('drops an empty to address rather than addressing nobody', function () {
        // send('', ...) produced getTo() === [''], which SMTP writes to the wire as
        // RCPT TO:<> and PhpMail hands to mail(''). cc and bcc have always mapped ''
        // to []; to was the one recipient field that did not, so the two empty
        // spellings of the same parameter failed two different ways.
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getTo() === [];
            })
            ->andReturn(true);

        $this->mailer->send('', 'Test', 'Body');
    });

    /*
     * Audit-081702 F11-A. The scalar spelling was closed; the array spelling was
     * not. normalizeRecipients() returned arrays untouched, so send(['']) kept
     * getTo() === [''] and addressesAreValid() skips empty strings, so it
     * validated and the transport ran: SMTP issued a literal RCPT TO:<> for every
     * empty slot, and PhpMail emitted a bare 'Cc: ' header.
     */
    it('drops an empty slot from an array of to addresses', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getTo() === [];
            })
            ->andReturn(true);

        $this->mailer->send([''], 'Test', 'Body');
    });

    it('keeps the real addresses when an array mixes empty slots in', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                // re-indexed, not ['', 1 => 'user@example.com']
                return $message->getTo() === ['user@example.com']
                    && $message->getCc() === ['cc@example.com']
                    && $message->getBcc() === [];
            })
            ->andReturn(true);

        $this->mailer->send(['', 'user@example.com'], 'Test', 'Body', [
            'cc' => ['', 'cc@example.com'],
            'bcc' => ['', null],
        ]);
    });

    /*
     * Audit 2026-08-19, S14. Recipients are normalized before Message strips
     * CR/LF from every header-bound field, so an address that is nothing but
     * CR/LF survived normalization, became '' inside Message, was skipped by
     * addressesAreValid()'s empty-string continue, and reached the wire as
     * RCPT TO:<>. Message now drops an address that stripping emptied, in the
     * same place the empty string is created. Not a security bug - an
     * injection attempt collapses to an invalid address and is rejected - but
     * a confusing failed send.
     */
    it('drops a recipient that is nothing but CR/LF once the header strip empties it', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getTo() === ['user@example.com']
                    && $message->getCc() === []
                    && $message->getBcc() === [];
            })
            ->andReturn(true);

        $this->mailer->send(["\r\n", 'user@example.com'], 'Test', 'Body', [
            'cc' => ["\n"],
            'bcc' => ["\r"],
        ]);
    });

    it('sends email with cc and bcc', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getCc() === ['cc@example.com']
                    && $message->getBcc() === ['bcc@example.com'];
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'cc' => 'cc@example.com',
            'bcc' => 'bcc@example.com',
        ]);

        expect($result)->toBeTrue();
    });

    it('sends email with reply-to', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getReplyTo() === 'reply@example.com';
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'replyTo' => 'reply@example.com',
        ]);

        expect($result)->toBeTrue();
    });

    it('sends email with priority flag', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->isPriority() === true;
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'priority' => true,
        ]);

        expect($result)->toBeTrue();
    });

    it('accepts a truthy non-boolean priority', function () {
        // Message::$priority is a bool under strict_types, so passing the option
        // through uncast turned 'priority' => 1 into a TypeError several calls
        // deep. Pro's mailer casts; this one has to as well.
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->isPriority() === true;
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'priority' => 1,
        ]);

        expect($result)->toBeTrue();
    });

    it('accepts a falsy non-boolean priority', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->isPriority() === false;
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'priority' => 0,
        ]);

        expect($result)->toBeTrue();
    });

    it('replaces template variables in text body', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return str_contains($message->getText(), 'Hello, John!');
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Hello, {{ name }}!', [
            'data' => ['name' => 'John'],
        ]);

        expect($result)->toBeTrue();
    });

    it('replaces template variables without spaces', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return str_contains($message->getText(), 'Hello, Jane!');
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Hello, {{name}}!', [
            'data' => ['name' => 'Jane'],
        ]);

        expect($result)->toBeTrue();
    });

    it('escapes html in template variables for html body', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return str_contains($message->getHtml(), '&lt;script&gt;');
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Text', [
            'html' => '<p>{{ content }}</p>',
            'data' => ['content' => '<script>alert("xss")</script>'],
        ]);

        expect($result)->toBeTrue();
    });

    it('overrides from address via options', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getFromAddress() === 'custom@example.com';
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'fromAddress' => 'custom@example.com',
        ]);

        expect($result)->toBeTrue();
    });

    it('uses from address from config when not in options', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return $message->getFromAddress() === 'noreply@example.com';
            })
            ->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body');

        expect($result)->toBeTrue();
    });

});

describe('Mailer::sendFormData()', function () {

    it('sends form data as formatted email', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return str_contains($message->getText(), 'Name: John')
                    && str_contains($message->getText(), 'Email: john@example.com')
                    && str_contains($message->getHtml(), '<table');
            })
            ->andReturn(true);

        $formData = [
            'name' => 'John',
            'email' => 'john@example.com',
        ];

        $result = $this->mailer->sendFormData('admin@example.com', 'New Submission', $formData);

        expect($result)->toBeTrue();
    });

    it('excludes specified fields from form data', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return str_contains($message->getText(), 'Name: John')
                    && ! str_contains($message->getText(), 'password');
            })
            ->andReturn(true);

        $formData = [
            'name' => 'John',
            'password' => 'secret123',
        ];

        $result = $this->mailer->sendFormData('admin@example.com', 'New Submission', $formData, [
            'exclude' => ['password'],
        ]);

        expect($result)->toBeTrue();
    });

    it('excludes fields starting with underscore', function () {
        $this->transport->shouldReceive('send')
            ->once()
            ->withArgs(function (Message $message) {
                return str_contains($message->getText(), 'Name: John')
                    && ! str_contains($message->getText(), '_token');
            })
            ->andReturn(true);

        $formData = [
            'name' => 'John',
            '_token' => 'abc123',
        ];

        $result = $this->mailer->sendFormData('admin@example.com', 'New Submission', $formData);

        expect($result)->toBeTrue();
    });

});

describe('Mailer::attach()', function () {

    it('throws InvalidArgumentException with upgrade message', function () {
        $this->mailer->attach('/path/to/file.pdf');
    })->throws(InvalidArgumentException::class, 'Email attachments require Flick Pro');

});

describe('Mailer::attachContent()', function () {

    it('throws InvalidArgumentException with upgrade message', function () {
        $this->mailer->attachContent('content', 'file.txt', 'text/plain');
    })->throws(InvalidArgumentException::class, 'Email attachments require Flick Pro');

});

describe('Mailer::clearAttachments()', function () {

    it('throws InvalidArgumentException with upgrade message', function () {
        $this->mailer->clearAttachments();
    })->throws(InvalidArgumentException::class, 'Email attachments require Flick Pro');

});

describe('Mailer attachments via options', function () {

    it('throws the upgrade message when send() options include attachments', function () {
        $this->mailer->send('user@example.com', 'Subject', 'Body', [
            'attachments' => ['/path/to/file.pdf'],
        ]);
    })->throws(InvalidArgumentException::class, 'Email attachments require Flick Pro');

    it('throws the upgrade message when sendFormData() options include attachments', function () {
        $this->mailer->sendFormData('user@example.com', 'Subject', ['name' => 'Jo'], [
            'attachments' => ['/path/to/file.pdf'],
        ]);
    })->throws(InvalidArgumentException::class, 'Email attachments require Flick Pro');

});

describe('Mailer address validation', function () {

    it('rejects a reply-to containing CR/LF injection', function () {
        $this->transport->shouldNotReceive('send');

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'replyTo' => "reply@example.com\r\nBcc: evil@example.com",
        ]);

        expect($result)->toBeFalse();
    });

    it('rejects an invalid recipient address', function () {
        $this->transport->shouldNotReceive('send');

        $result = $this->mailer->send('not-an-email', 'Test', 'Body');

        expect($result)->toBeFalse();
    });

    it('rejects an invalid cc address', function () {
        $this->transport->shouldNotReceive('send');

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'cc' => 'broken-cc-address',
        ]);

        expect($result)->toBeFalse();
    });

    it('sends when all addresses are valid', function () {
        $this->transport->shouldReceive('send')->once()->andReturn(true);

        $result = $this->mailer->send('user@example.com', 'Test', 'Body', [
            'cc' => 'cc@example.com',
            'replyTo' => 'reply@example.com',
        ]);

        expect($result)->toBeTrue();
    });

});
