<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\TagList;
use PHPUnit\Framework\TestCase;

final class TagListTest extends TestCase
{
    public function testSplitsOnCommasAndTrimsSpaces(): void
    {
        self::assertSame(['street art', 'Belgique', 'village abandonné'], TagList::parse(' street  art, Belgique ,village abandonné,, '));
    }

    public function testRemovesDuplicatesIgnoringCaseAndAccents(): void
    {
        self::assertSame(['Église', 'Arras'], TagList::parse('Église, eglise, ÉGLISE, Arras, arras'));
    }

    public function testCapsLengthAndCount(): void
    {
        self::assertSame(TagList::MAX_LENGTH, mb_strlen(TagList::parse(str_repeat('é', 200))[0]));
        $many = implode(',', array_map(static fn (int $i): string => 'mot' . $i, range(1, 50)));
        self::assertCount(TagList::MAX_TAGS, TagList::parse($many));
    }

    public function testIgnoresTagsWithoutLettersOrDigits(): void
    {
        self::assertSame(['F-14'], TagList::parse('---, F-14, !!!'));
    }

    public function testSlugIsTheUrlForm(): void
    {
        self::assertSame('village-abandonne', TagList::slug('Village abandonné'));
        self::assertSame('f-14', TagList::slug('F-14'));
    }

    public function testJoinIsTheEditableForm(): void
    {
        self::assertSame('street art, Belgique', TagList::join(['street art', 'Belgique']));
    }
}
