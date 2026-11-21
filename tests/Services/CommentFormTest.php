<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\CommentForm;
use App\Services\ContactForm;
use PHPUnit\Framework\TestCase;

final class CommentFormTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';
    private const NOW = 1_800_000_000;

    private function input(array $override = []): array
    {
        return $override + [
            'name' => '  Marie  ',
            'message' => " Superbe lumière sur le beffroi !\n",
            'website' => '',
            'ts' => ContactForm::token(self::KEY, self::NOW - 30),
        ];
    }

    public function testAcceptsAValidComment(): void
    {
        $r = CommentForm::validate($this->input(), self::KEY, self::NOW);

        self::assertSame([], $r['errors']);
        self::assertFalse($r['spam']);
        self::assertSame(['name' => 'Marie', 'message' => 'Superbe lumière sur le beffroi !'], $r['data']);
    }

    public function testRequiresNameAndMessage(): void
    {
        $r = CommentForm::validate($this->input(['name' => ' ', 'message' => 'a']), self::KEY, self::NOW);

        self::assertArrayHasKey('name', $r['errors']);
        self::assertArrayHasKey('message', $r['errors']);
    }

    public function testCapsLengths(): void
    {
        $r = CommentForm::validate($this->input(['name' => str_repeat('n', 101), 'message' => str_repeat('m', 2001)]), self::KEY, self::NOW);

        self::assertArrayHasKey('name', $r['errors']);
        self::assertArrayHasKey('message', $r['errors']);
    }

    public function testFlagsRobots(): void
    {
        self::assertTrue(CommentForm::validate($this->input(['website' => 'http://spam']), self::KEY, self::NOW)['spam']);
        self::assertTrue(CommentForm::validate($this->input(['ts' => 'faux']), self::KEY, self::NOW)['spam']);
        // Envoyé moins de 3 secondes après l'affichage
        self::assertTrue(CommentForm::validate($this->input(['ts' => ContactForm::token(self::KEY, self::NOW - 1)]), self::KEY, self::NOW)['spam']);
    }

    public function testRejectsLinksSpam(): void
    {
        $r = CommentForm::validate($this->input(['message' => 'Voir http://a.example http://b.example http://c.example']), self::KEY, self::NOW);

        self::assertTrue($r['spam']);
    }
}
