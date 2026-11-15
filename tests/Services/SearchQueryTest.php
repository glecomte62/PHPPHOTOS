<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\SearchQuery;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    public function testSplitsWordsAndDropsFrenchStopWords(): void
    {
        self::assertSame(['pont', 'brooklyn'], SearchQuery::terms('  Le pont de Brooklyn '));
        self::assertSame(['statue', 'liberté'], SearchQuery::terms("la statue de l'Liberté"));
        self::assertSame(['côte', 'opale'], SearchQuery::terms("Côte d’Opale"));
    }

    public function testKeepsShortMeaningfulTokens(): void
    {
        self::assertSame(['7', 'av'], SearchQuery::terms('7 Av'));
        self::assertSame(['f-14', 'tomcat'], SearchQuery::terms('F-14 Tomcat'));
    }

    public function testRemovesDuplicatesAndLimitsTermCount(): void
    {
        self::assertSame(['paris'], SearchQuery::terms('Paris paris PARIS'));
        self::assertCount(SearchQuery::MAX_TERMS, SearchQuery::terms('un deux trois quatre cinq six sept huit neuf'));
    }

    public function testEmptyOrStopWordOnlyQueriesGiveNoTerms(): void
    {
        self::assertSame([], SearchQuery::terms(''));
        self::assertSame([], SearchQuery::terms('   '));
        self::assertSame([], SearchQuery::terms('le la les de'));
    }

    public function testNormalizeTrimsAndCapsLength(): void
    {
        self::assertSame('pont de Brooklyn', SearchQuery::normalize("  pont   de\tBrooklyn "));
        self::assertSame(SearchQuery::MAX_LENGTH, mb_strlen(SearchQuery::normalize(str_repeat('é', 500))));
        self::assertSame('', SearchQuery::normalize(['tableau']));
    }

    public function testLikePatternEscapesWildcards(): void
    {
        self::assertSame('%100\\%%', SearchQuery::likePattern('100%'));
        self::assertSame('%a\\_b%', SearchQuery::likePattern('a_b'));
        self::assertSame('%c\\\\d%', SearchQuery::likePattern('c\\d'));
    }
}
