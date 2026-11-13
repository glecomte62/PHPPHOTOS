<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Mailer;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    public function testBuildsUtf8MessageWithReplyTo(): void
    {
        $msg = Mailer::buildMessage('site@exemple.fr', 'moi@exemple.fr', 'Bonjour é', "Ligne 1\nLigne 2", 'visiteur@exemple.org');

        self::assertStringContainsString("From: guillaumelecomte.net <site@exemple.fr>\r\n", $msg);
        self::assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('Bonjour é') . '?=', $msg);
        self::assertStringContainsString("Reply-To: visiteur@exemple.org\r\n", $msg);
        [, $body] = explode("\r\n\r\n", $msg, 2);
        self::assertSame("Ligne 1\nLigne 2", base64_decode(str_replace("\r\n", '', $body)));
    }

    public function testRejectsHeaderInjection(): void
    {
        $msg = Mailer::buildMessage('site@exemple.fr', 'moi@exemple.fr', "Sujet\r\nBcc: x@y.z", 'corps', "a@b.c\r\nBcc: x@y.z");
        self::assertStringNotContainsString("\r\nBcc:", $msg);
        self::assertStringNotContainsString('Reply-To:', $msg);
    }
}
