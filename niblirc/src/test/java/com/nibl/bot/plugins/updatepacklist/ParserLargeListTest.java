package com.nibl.bot.plugins.updatepacklist;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assumptions.assumeTrue;

import java.io.RandomAccessFile;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.SQLException;
import java.util.LinkedList;
import java.util.List;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import com.nibl.bot.testing.TestBot;

/**
 * End-to-end runs of the real packlist parser ({@link PackParseFunctions}.
 * {@code randomAccessFileToPacks}) over large inputs.
 *
 * <p>The generated-list test always runs and exercises the parser at scale.
 * The two real-packlist tests read the sample files the owner keeps in
 * {@code botlists/} (git-ignored runtime data, so they are only present when
 * the test is run from the IDE against a checkout that has them). They are
 * skipped when the files are missing, which keeps the Docker/CI build green.
 */
class ParserLargeListTest {

    private static TestBot bot;
    private static AbstractDistroBot distroBot;
    private static PackParseFunctions packParseFunctions;

    @TempDir
    static Path tempDir;

    @BeforeAll
    static void setUp() throws SQLException {
        bot = new TestBot();
        distroBot = new HttpDistroBot(bot, 1, "FakeDistro", "http://example.invalid/list.txt", "HTTP",
                1, null, null, new LinkedList<>(), 0, 0, "Tester", PackParseFunctions.PARSER_NORMAL_ID);
        packParseFunctions = new PackParseFunctions(bot);
    }

    @Test
    void parsesLargeGeneratedPacklist() throws Exception {
        Path file = tempDir.resolve("generated-large.txt");
        int total = 50_000;
        StringBuilder sb = new StringBuilder(total * 42);
        for (int i = 1; i <= total; i++) {
            String size = (i % 5 == 0) ? "1G" : "100M";
            sb.append('#').append(i).append(" 1x [").append(size).append("] Show - ").append(i).append(" [1080p].mkv\n");
        }
        Files.writeString(file, sb);

        long start = System.nanoTime();
        List<Pack> packs = parse(file);
        long millis = (System.nanoTime() - start) / 1_000_000;

        assertEquals(total, packs.size());
        assertEquals(1, packs.get(0).getNumber());
        assertEquals("Show - 1 [1080p].mkv", packs.get(0).getName());
        assertEquals("100M", packs.get(0).getSize());
        assertEquals((long) (100 * Math.pow(1024, 2)), packs.get(0).getSizeKBits());
        assertEquals(total, packs.get(packs.size() - 1).getNumber());

        System.out.printf("Parsed %d generated packs in %d ms%n", total, millis);
    }

    @Test
    void parsesRealSakuraservPacklist() throws Exception {
        Path file = locatePacklist("sakuraserv.txt");
        assumeTrue(file != null, "sakuraserv.txt not found under botlists/ — run from the niblirc module");

        long start = System.nanoTime();
        List<Pack> packs = parse(file);
        long millis = (System.nanoTime() - start) / 1_000_000;

        assertEquals(220_032, packs.size());
        assertEquals(1, packs.get(0).getNumber());
        assertEquals("9.1M", packs.get(0).getSize());
        assertEquals((long) (9.1 * Math.pow(1024, 2)), packs.get(0).getSizeKBits());

        Pack third = packs.get(2);
        assertEquals(3, third.getNumber());
        assertEquals("(SAO AMV) 30 Seconds to mars - The Fantasy.mp4", third.getName());
        assertEquals("124M", third.getSize());

        assertEquals(220_032, packs.get(packs.size() - 1).getNumber());
        assertEquals("151M", packs.get(packs.size() - 1).getSize());

        System.out.printf("Parsed sakuraserv.txt: %d packs in %d ms%n", packs.size(), millis);
    }

    @Test
    void parsesRealGarbledPacklist() throws Exception {
        Path file = locatePacklist("garbled.txt");
        assumeTrue(file != null, "garbled.txt not found under botlists/ — run from the niblirc module");

        long start = System.nanoTime();
        List<Pack> packs = parse(file);
        long millis = (System.nanoTime() - start) / 1_000_000;

        assertEquals(5_337, packs.size());
        assertEquals(1, packs.get(0).getNumber());
        assertEquals("31M", packs.get(0).getSize());
        assertEquals((long) (31 * Math.pow(1024, 2)), packs.get(0).getSizeKBits());

                assertEquals(9, packs.get(8).getNumber());
                assertEquals("82M", packs.get(8).getSize());
                assertEquals("(その他) [安彦良和] 機動戦士ガンダム THE ORIGIN 公式ガイドブック.zip", packs.get(8).getName());

                System.out.printf("Parsed garbled.txt: %d packs in %d ms%n", packs.size(), millis);
    }

    private static List<Pack> parse(Path file) throws Exception {
        try (RandomAccessFile raf = new RandomAccessFile(file.toFile(), "r")) {
            return packParseFunctions.randomAccessFileToPacks(distroBot, raf);
        }
    }

    private static Path locatePacklist(String fileName) {
        String[] bases = {"botlists", "niblirc/botlists", "../niblirc/botlists", "NIBL/niblirc/botlists"};
        for (String base : bases) {
            Path candidate = Paths.get(base, fileName);
            if (Files.isRegularFile(candidate)) {
                return candidate.toAbsolutePath();
            }
        }
        return null;
    }
}