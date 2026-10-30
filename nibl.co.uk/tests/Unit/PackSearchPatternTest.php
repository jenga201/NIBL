<?php

namespace Tests\Unit;

use App\Models\Pack;
use PHPUnit\Framework\TestCase;

class PackSearchPatternTest extends TestCase
{
    public function test_single_term_is_used_verbatim(): void
    {
        self::assertSame('frieren', Pack::buildSearchPattern('frieren'));
    }

    public function test_terms_are_joined_in_order(): void
    {
        self::assertSame('sousou.*no.*frieren', Pack::buildSearchPattern(' sousou   no frieren '));
    }

    public function test_regex_metacharacters_are_escaped(): void
    {
        self::assertSame(
            '\[SubsPlease\].*\(1080p\)\.mkv',
            Pack::buildSearchPattern('[SubsPlease] (1080p).mkv')
        );
    }

    public function test_empty_query_produces_empty_pattern(): void
    {
        self::assertSame('', Pack::buildSearchPattern('   '));
    }
}
