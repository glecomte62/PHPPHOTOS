<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\ContactForm;
use PHPUnit\Framework\TestCase;

final class ContactFormTest extends TestCase
{
    private const KEY = 'test-key';
    private const NOW = 2_000_000;

    private function input(array $override = []): array
    {
        return $override + [
            'name' => 'Jeanne',
            'email' => 'jeanne@exemple.fr',
            'message' => 'Bonjour, j\'aime beaucoup vos vues du ciel !',
            'website' => '',
            'ts' => ContactForm::token(self::KEY, self::NOW - 30),
        ];
    }

    public function testValidSubmission(): void
    {
        $r = ContactForm::validate($this->input(), self::KEY, self::NOW);
        self::assertSame([], $r['errors']);
        self::assertFalse($r['spam']);
        self::assertSame('Jeanne', $r['data']['name']);
    }

    public function testFieldErrors(): void
    {
        $r = ContactForm::validate($this->input(['name' => '', 'email' => 'pas-un-email', 'message' => 'court']), self::KEY, self::NOW);
        self::assertSame(['name', 'email', 'message'], array_keys($r['errors']));
    }

    public function testSpamSignals(): void
    {
        self::assertTrue(ContactForm::validate($this->input(['website' => 'http://spam']), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => ContactForm::token(self::KEY, self::NOW - 1)]), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => ContactForm::token(self::KEY, self::NOW - 90000)]), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => ContactForm::token('autre-cle', self::NOW - 30)]), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => 'n importe quoi']), self::KEY, self::NOW)['spam']);
    }
}
