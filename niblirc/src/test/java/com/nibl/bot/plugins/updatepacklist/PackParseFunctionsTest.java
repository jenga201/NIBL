package com.nibl.bot.plugins.updatepacklist;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.io.RandomAccessFile;
import java.nio.file.Path;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.util.LinkedList;
import java.util.List;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import com.nibl.bot.testing.TestBot;
import com.nibl.bot.testing.TestFiles;

/**
 * Tests for {@link PackParseFunctions}: size-to-kbits conversion, pack list
 * change detection and the parser fallback in randomAccessFileToPacks.
 * Runs against the in-memory H2 database; no IRC or network access.
 */
class PackParseFunctionsTest {

    private static TestBot bot;
    private static PackParseFunctions ppf;
    private static AbstractDistroBot sizeBot;

    @TempDir
    static Path tempDir;

    @BeforeAll
    static void setUp() {
        try {
            bot = new TestBot();
        } catch (SQLException e) {
            throw new IllegalStateException("Failed to start the in-memory test database", e);
        }
        ppf = new PackParseFunctions(bot);
        sizeBot = new HttpDistroBot(bot, 42, "SizeBot", "http://example.invalid/list.txt", "HTTP", 1, null, null,
                new LinkedList<>(), 0, 0, "Tester", PackParseFunctions.PARSER_NORMAL_ID);
    }

    @Test
    void buildPackConvertsMegabytes() {
        Pack pack = ppf.buildPack(sizeBot, 7, "Some Show - 07", "9.1M");
        assertEquals(42, pack.getBotId());
        assertEquals("SizeBot", pack.getBotName());
        assertEquals(7, pack.getNumber());
        assertEquals("Some Show - 07", pack.getName());
        assertEquals("9.1M", pack.getSize());
        assertEquals((long) (9.1 * Math.pow(1024, 2)), pack.getSizeKBits());
        assertEquals(-1, pack.getEpisodeNumber());
    }

    @Test
    void buildPackTrimsSizeAndHandlesUnits() {
        assertEquals((long) (19 * Math.pow(1024, 2)), ppf.buildPack(sizeBot, 1, "n", " 19M").getSizeKBits());
        assertEquals(512L, ppf.buildPack(sizeBot, 1, "n", "512K").getSizeKBits());
        assertEquals((long) (1.2 * Math.pow(1024, 3)), ppf.buildPack(sizeBot, 1, "n", "1.2G").getSizeKBits());
        assertEquals((long) (2 * Math.pow(1024, 4)), ppf.buildPack(sizeBot, 1, "n", "2T").getSizeKBits());
    }

    @Test
    void buildPackStripsLessThanPrefix() {
        assertEquals((long) (1.2 * Math.pow(1024, 3)), ppf.buildPack(sizeBot, 1, "n", "<1.2G").getSizeKBits());
    }

    @Test
    void buildPackUnparseableSizeFallsBackToZero() {
        Pack pack = ppf.buildPack(sizeBot, 1, "n", "oops");
        assertEquals(0L, pack.getSizeKBits());
    }

    @Test
    void packListChangedFalseWhenIdenticalAndCarriesOverMetadata() {
        Timestamp oldModified = Timestamp.valueOf("2026-01-01 00:00:00");
        Pack oldPack = new Pack(42, 10, "SizeBot", "Show - 01", "1.2G", 123L, 7, oldModified);
        sizeBot.setListing(new LinkedList<>(List.of(oldPack)));

        Pack fresh = new Pack(42, 10, "SizeBot", "Show - 01", "1.2G", 123L, -1, new Timestamp(System.currentTimeMillis()));
        List<Pack> newData = new LinkedList<>(List.of(fresh));

        assertFalse(ppf.packListChanged(sizeBot, newData));
        assertEquals(oldModified, fresh.getLastModified(), "unchanged packs keep their original last_modified");
        assertEquals(7, fresh.getEpisodeNumber(), "unchanged packs keep their episode number");
    }

    @Test
    void packListChangedTrueOnRenameAndDerivesEpisodeNumber() {
        Timestamp oldModified = Timestamp.valueOf("2026-01-01 00:00:00");
        sizeBot.setListing(new LinkedList<>(List.of(new Pack(42, 10, "SizeBot", "Show - 01", "1.2G", 123L, 1, oldModified))));

        Pack renamed = new Pack(42, 10, "SizeBot", "Show - 05", "1.2G", 123L, -1, new Timestamp(System.currentTimeMillis()));
        List<Pack> newData = new LinkedList<>(List.of(renamed));

        assertTrue(ppf.packListChanged(sizeBot, newData));
        assertEquals(5, renamed.getEpisodeNumber());
    }

    @Test
    void packListChangedTrueOnSizeChangeAndOnCountChange() {
        Timestamp oldModified = Timestamp.valueOf("2026-01-01 00:00:00");
        Pack oldPack = new Pack(42, 10, "SizeBot", "Show - 01", "1.2G", 123L, 1, oldModified);
        sizeBot.setListing(new LinkedList<>(List.of(oldPack)));

        Pack resized = new Pack(42, 10, "SizeBot", "Show - 01", "1.3G", 456L, -1, new Timestamp(System.currentTimeMillis()));
        assertTrue(ppf.packListChanged(sizeBot, new LinkedList<>(List.of(resized))));

        Pack same = new Pack(42, 10, "SizeBot", "Show - 01", "1.2G", 123L, -1, new Timestamp(System.currentTimeMillis()));
        Pack extra = new Pack(42, 11, "SizeBot", "Show - 02", "1.2G", 123L, -1, new Timestamp(System.currentTimeMillis()));
        assertTrue(ppf.packListChanged(sizeBot, new LinkedList<>(List.of(same, extra))));
    }

    @Test
    void randomAccessFileToPacksFallsBackToWorkingParserAndPersistsParserId() throws Exception {
        bot.insertBot("ParserProbe", "http://example.invalid/list.txt", "HTTP", "Tester", 1, 0);
        UpdatePackListDAO dao = (UpdatePackListDAO) bot.getDAOFactory().getDAO("UpdatePackListDAO");
        AbstractDistroBot probeBot = dao.getBotFromDatabase("ParserProbe");
        assertEquals(0, probeBot.getParserId());

        try (RandomAccessFile raf = TestFiles.fixtureAsRandomAccessFile("/fixtures/botlist-normal.txt", tempDir)) {
            List<Pack> packs = ppf.randomAccessFileToPacks(probeBot, raf);
            assertEquals(7, packs.size());
        }

        assertEquals(PackParseFunctions.PARSER_NORMAL_ID, probeBot.getParserId());
        assertEquals(PackParseFunctions.PARSER_NORMAL_ID, dao.getBotFromDatabase("ParserProbe").getParserId(),
                "the discovered parser id must be persisted");
    }

    @Test
    void parserEnumLookupById() {
        assertEquals(PackParseFunctions.Parser.PARSER_NORMAL, PackParseFunctions.Parser.PARSER_NULL.getParserById(1));
        assertEquals(PackParseFunctions.Parser.PARSER_DINOEX, PackParseFunctions.Parser.PARSER_NULL.getParserById(2));
        assertEquals(PackParseFunctions.Parser.PARSER_NULL, PackParseFunctions.Parser.PARSER_NULL.getParserById(99));
    }
}
