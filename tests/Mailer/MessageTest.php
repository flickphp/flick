<?php

use Flick\Mailer\Message\Message;

describe('Message', function () {

    it('stores and returns from address', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getFromAddress())->toBe('from@example.com');
    });

    it('stores and returns from name', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test Sender',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getFromName())->toBe('Test Sender');
    });

    it('RFC-2047 encodes a non-ASCII subject but leaves ASCII subjects unchanged', function () {
        $ascii = new Message('from@example.com', '', ['to@example.com'], 'Hello there', 'Body');
        expect($ascii->getSubjectEncoded())->toBe('Hello there');

        $utf8 = new Message('from@example.com', '', ['to@example.com'], 'Grüße', 'Body');
        expect($utf8->getSubjectEncoded())
            ->toStartWith('=?UTF-8?B?')
            ->not->toContain('Grüße');
    });

    it('RFC-2047 encodes a non-ASCII from display name without quoting it', function () {
        $message = new Message('from@example.com', 'Grüße Team', ['to@example.com'], 'Subject', 'Body');

        $from = $message->getFromFormatted();
        expect($from)
            ->toContain('=?UTF-8?B?')
            ->toContain('<from@example.com>')
            ->not->toContain('"Grüße');
    });

    it('keeps an ASCII from display name quoted', function () {
        $message = new Message('from@example.com', 'Test Sender', ['to@example.com'], 'Subject', 'Body');

        expect($message->getFromFormatted())->toBe('"Test Sender" <from@example.com>');
    });

    it('formats from header with name', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test Sender',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getFromFormatted())->toBe('"Test Sender" <from@example.com>');
    });

    it('formats from header without name', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: '',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getFromFormatted())->toBe('from@example.com');
    });

    it('escapes quotes in from name', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test "Quoted" Name',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getFromFormatted())->toBe('"Test \"Quoted\" Name" <from@example.com>');
    });

    it('stores and returns recipients', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['user1@example.com', 'user2@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getTo())->toBe(['user1@example.com', 'user2@example.com']);
    });

    it('stores and returns subject', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'My Subject',
            text: 'Body'
        );

        expect($message->getSubject())->toBe('My Subject');
    });

    it('stores and returns text body', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Plain text body'
        );

        expect($message->getText())->toBe('Plain text body');
    });

    it('stores and returns html body', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Plain text',
            html: '<h1>HTML Body</h1>'
        );

        expect($message->getHtml())->toBe('<h1>HTML Body</h1>');
    });

    it('returns null for html when not set', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getHtml())->toBeNull();
    });

    it('stores and returns cc recipients', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            cc: ['cc1@example.com', 'cc2@example.com']
        );

        expect($message->getCc())->toBe(['cc1@example.com', 'cc2@example.com']);
    });

    it('stores and returns bcc recipients', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            bcc: ['bcc@example.com']
        );

        expect($message->getBcc())->toBe(['bcc@example.com']);
    });

    it('stores and returns reply-to', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            replyTo: 'reply@example.com'
        );

        expect($message->getReplyTo())->toBe('reply@example.com');
    });

    it('stores and returns priority flag', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body',
            priority: true
        );

        expect($message->isPriority())->toBeTrue();
    });

    it('defaults priority to false', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->isPriority())->toBeFalse();
    });

});

describe('Message header hygiene', function () {

    it('strips CR/LF from the subject', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: "Hello\r\nBcc: evil@example.com",
            text: 'Body'
        );

        expect($message->getSubject())
            ->not->toContain("\r")
            ->and($message->getSubject())->not->toContain("\n");
    });

    it('strips CR/LF from the from name', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: "Test\r\nBcc: evil@example.com",
            to: ['to@example.com'],
            subject: 'Subject',
            text: 'Body'
        );

        expect($message->getFromName())
            ->not->toContain("\r")
            ->and($message->getFromName())->not->toContain("\n");
    });

    it('strips CR/LF from recipient addresses and reply-to', function () {
        $message = new Message(
            fromAddress: "from@example.com\r\nBcc: evil@example.com",
            fromName: 'Test',
            to: ["to@example.com\r\nCc: evil@example.com"],
            subject: 'Subject',
            text: 'Body',
            cc: ["cc@example.com\nevil@example.com"],
            replyTo: "reply@example.com\r\nBcc: evil@example.com",
        );

        expect($message->getFromAddress())->not->toContain("\n");
        expect($message->getTo()[0])->not->toContain("\n")->not->toContain("\r");
        expect($message->getCc()[0])->not->toContain("\n");
        expect($message->getReplyTo())->not->toContain("\n")->not->toContain("\r");
    });

    it('preserves newlines in the body text', function () {
        $message = new Message(
            fromAddress: 'from@example.com',
            fromName: 'Test',
            to: ['to@example.com'],
            subject: 'Subject',
            text: "Line one\nLine two",
        );

        expect($message->getText())->toBe("Line one\nLine two");
    });

});
