package com.nibl.bot.plugins.updatepacklist;

import static org.junit.jupiter.api.Assertions.assertEquals;

import java.io.RandomAccessFile;
import java.nio.file.Path;
import java.sql.SQLException;
import java.util.LinkedList;
import java.util.List;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import com.nibl.bot.testing.TestBot;
import com.nibl.bot.testing.TestFiles;

/**
 * Tests {@link ParserNormal} against a fixture in the real iroffer botlist
 * format ({@code #<num> <downloads>x [<size>] <name>}), including header
 * lines, junk lines, malformed sizes and {@code <br>}-joined lines.
 */
class ParserNormalTest {

    private static TestBot bot;
    private static AbstractDistroBot distroBot;

    @TempDir
    static Path tempDir;

    @BeforeAll
    static void setUp() throws SQLException {
        bot = new TestBot();
        distroBot = new HttpDistroBot(bot, 1, "FakeDistro", "http://example.invalid/list.txt", "HTTP", 1, null, null,
                new LinkedList<>(), 0, 0, "Tester", PackParseFunctions.PARSER_NORMAL_ID);
    }

    private List<Pack> parseFixture() throws Exception {
        try (RandomAccessFile raf = TestFiles.fixtureAsRandomAccessFile("/fixtures/botlist-normal.txt", tempDir)) {
            return new ParserNormal().parse(distroBot, raf);
        }
    }

    @Test
    void parsesEveryPackLineAndSkipsJunk() throws Exception {
        List<Pack> packs = parseFixture();
        assertEquals(7, packs.size());
        for (int i = 0; i < packs.size(); i++) {
            assertEquals(i + 1, packs.get(i).getNumber(), "packs must keep file order");
        }
    }

    @Test
    void parsesSizeAndName() throws Exception {
        List<Pack> packs = parseFixture();

        Pack first = packs.get(0);
        assertEquals("9.1M", first.getSize());
        assertEquals("Addiction Anime Mix AMV.mp4", first.getName());
        assertEquals((long) (9.1 * Math.pow(1024, 2)), first.getSizeKBits());
        assertEquals("FakeDistro", first.getBotName());

        Pack bracketed = packs.get(2);
        assertEquals("(SAO AMV) 30 Seconds to Mars - The Fantasy.mp4", bracketed.getName());
        assertEquals((long) (124 * Math.pow(1024, 2)), bracketed.getSizeKBits());

        Pack gigabyte = packs.get(3);
        assertEquals("Long Show Name - 01 [1080p].mkv", gigabyte.getName());
        assertEquals((long) (1.5 * Math.pow(1024, 3)), gigabyte.getSizeKBits());
    }

    @Test
    void malformedSizeStillProducesPackWithZeroKBits() throws Exception {
        Pack malformed = parseFixture().get(4);
        assertEquals(5, malformed.getNumber());
        assertEquals("Malformed Size Pack", malformed.getName());
        assertEquals(0L, malformed.getSizeKBits());
    }

    @Test
    void brJoinedLineIsSplitIntoTwoPacks() throws Exception {
        List<Pack> packs = parseFixture();
        assertEquals("Pack Six", packs.get(5).getName());
        assertEquals("Pack Seven", packs.get(6).getName());
        assertEquals((long) (20 * Math.pow(1024, 2)), packs.get(6).getSizeKBits());
    }

    @Test
    void decodesUtf8PackNames() throws Exception {
        try (RandomAccessFile raf = TestFiles.fixtureAsRandomAccessFile("/fixtures/botlist-utf8.txt", tempDir)) {
            List<Pack> packs = new ParserNormal().parse(distroBot, raf);

            assertEquals(2, packs.size());
            assertEquals("(その他) [安彦良和] 機動戦士ガンダム THE ORIGIN 公式ガイドブック.zip", packs.get(0).getName());
            assertEquals("イリヤの空、UFOの夏 [1080p].mkv", packs.get(1).getName());
        }
    }
}
